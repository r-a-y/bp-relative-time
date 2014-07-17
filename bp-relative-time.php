<?php

add_action( 'bp_loaded', array( 'BP_Relative_Time', 'init' ) );

/**
 * BP Relative Time Core
 */
class BP_Relative_Time {
	/**
	 * Static initializer.
	 */
	public static function init() {
		return new self();
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		// assets
		add_action( 'bp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		/** hook stuff **/
		// @todo - messages, blogs directory

		// activity
		add_filter( 'bp_activity_allowed_tags',          array( $this, 'activity_allowed_tags' ) );
		add_filter( 'bp_activity_time_since',            array( $this, 'activity_time_since' ), 10, 2 );
		add_filter( 'bp_activity_comment_date_recorded', array( $this, 'activity_comment_date_recorded' ), 10, 2 );

		// members
		add_filter( 'bp_member_last_active', array( $this, 'member_last_active' ) );
		add_filter( 'bp_member_registered',  array( $this, 'member_registered' ) );

		// groups
		add_filter( 'bp_get_group_date_created', array( $this, 'group_date_created' ) );
		add_filter( 'bp_get_group_last_active',  array( $this, 'group_last_active' ) );
	}

	/**
	 * Enqueue our scripts.
	 */
	public function enqueue_scripts() {
		$load = apply_filters( 'bp_relative_time_enqueue', is_buddypress() );

		if ( false == (bool) $load ) {
			return;
		}

		// register our scripts - using the cloud!
		wp_register_script( 'bp-moment', '//cdnjs.cloudflare.com/ajax/libs/moment.js/2.7.0/moment.min.js', array(), '2.7' );
		wp_register_script( 'bp-jquery-livestamp', '//cdnjs.cloudflare.com/ajax/libs/livestamp/1.1.2/livestamp.min.js', array( 'jquery', 'bp-moment' ), '1.1.2' );

		// enqueue livestamp.js
		wp_enqueue_script( 'bp-jquery-livestamp' );

		// we're only localizing the relative time strings for moment.js since that's
		// all we need for now
		wp_localize_script( 'bp-moment', 'BP_Moment_i18n', array(
			'future' => __( 'in %s',         'buddypress' ),
			'past'   => __( '%s ago',        'buddypress' ),
			's'      => __( 'a few seconds', 'buddypress' ),
			'm'      => __( 'a minute',      'buddypress' ),
			'mm'     => __( '%d minutes',    'buddypress' ),
			'h'      => __( 'an hour',       'buddypress' ),
			'hh'     => __( '%d hours',      'buddypress' ),
			'd'      => __( 'a day',         'buddypress' ),
			'dd'     => __( '%d days',       'buddypress' ),
			'M'      => __( 'a month',       'buddypress' ),
			'MM'     => __( '%d months',     'buddypress' ),
			'y'      => __( 'a year',        'buddypress' ),
			'yy'     => __( '%d years',      'buddypress' ),
		) );

		add_action( 'wp_footer', array( $this, 'inline_js' ) );
	}

	/**
	 * Inline JS.
	 */
	public function inline_js() {
		// if livestamp hasn't printed, stop now!
		if ( false === wp_script_is( 'bp-jquery-livestamp', 'done' ) ) {
			return;
		}

	?>

		<script type="text/javascript">
			jq(function() {
				moment.lang( 'en', {
					relativeTime : BP_Moment_i18n
				});
		        });

		</script>

	<?php
	}

	/**
	 * Allow the <time> element when rendering items in the activity loop.
	 */
	public function activity_allowed_tags( $retval ) {
		$retval['span']['data-livestamp'] = array();
		return $retval;
	}

	public function activity_time_since( $retval, $activity ) {
		$time_element = sprintf(
			'<span data-livestamp="%1$s">%2$s</span>',
			$this->get_iso8601_date( $activity->date_recorded ),
			str_replace( array( '<span class="time-since">', '</span>' ), '', $retval )
		);

		return '<span class="time-since">' . $time_element . '</span>';
	}

	public function activity_comment_date_recorded( $retval ) {
		global $activities_template;

		return sprintf(
			'<span data-livestamp="%1$s">%2$s</span>',
			$this->get_iso8601_date( $activities_template->activity->current_comment->date_recorded ),
			$retval
		);
	}

	public function member_last_active( $retval ) {
		global $members_template;

		return sprintf(
			// ugh... inconsistencies!
			// groups component has the 'active' string separated from the timestamp,
			// while the members component has it all-in-one.  let's separate the two to
			// match the groups component
			__( 'active %s', 'buddypress' ),
			sprintf(
				'<span data-livestamp="%1$s">%2$s</span>',
				$this->get_iso8601_date( $members_template->member->last_activity ),
				bp_core_time_since( $members_template->member->last_activity )
			)
		);
	}

	public function member_registered( $retval ) {
		global $members_template;

		return sprintf(
			// ugh... inconsistencies!
			// groups component has the 'active' string separated from the timestamp,
			// while the members component has it all-in-one.  let's separate the two to
			// match the groups component
			_x( 'registered %s', 'Records the timestamp that the user registered into the activy stream', 'buddypress' ),
			sprintf(
				'<span data-livestamp="%1$s">%2$s</span>',
				$this->get_iso8601_date( $members_template->member->user_registered ),
				bp_core_time_since( $members_template->member->user_registered )
			)
		);
	}

	public function group_date_created( $retval ) {
		global $groups_template;

		if ( empty( $groups_template->group ) ) {
			return $retval;
		}

		return sprintf(
			'<span data-livestamp="%1$s">%2$s</span>',
			$this->get_iso8601_date( $groups_template->group->date_created ),
			$retval
		);
	}

	public function group_last_active( $retval ) {
		global $groups_template;

		if ( empty( $groups_template->group ) ) {
			return $retval;
		}

		$last_active = ! empty( $groups_template->group->last_activity ) ? $groups_template->group->last_activity : false;

		if ( false === $last_active ) {
			$last_active = groups_get_groupmeta( $groups_template->group->id, 'last_activity' );
		}

		return sprintf(
			'<span data-livestamp="%1$s">%2$s</span>',
			$this->get_iso8601_date( $last_active ),
			$retval
		);
	}

	/**
	 * Convert a date to an ISO-8601 date.
	 *
	 * @param string String of date to convert. Timezone should be UTC.
	 * @return string
	 */
	public function get_iso8601_date( $timestamp = '' ) {
		$date = new DateTime( $timestamp, new DateTimeZone( 'UTC' ) );
		return $date->format( DateTime::ISO8601 );
	}
}