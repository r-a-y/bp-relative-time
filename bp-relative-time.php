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

		// Remove core BP moment locale; this plugin handles moment differently.
		// Works only for English installs though...
		add_filter( 'bp_core_register_common_scripts', function( $retval ) {
			if ( isset( $retval['bp-moment-locale'] ) ) {
				unset( $retval['bp-moment-locale'] );
			}
			return $retval;
		} );

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

		// enqueue livestamp.js
		wp_enqueue_script( 'bp-livestamp' );

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

		add_action( 'wp_footer', array( $this, 'inline_js' ), 9999 );
	}

	/**
	 * Inline JS.
	 */
	public function inline_js() {
		// if livestamp hasn't printed, stop now!
		if ( false === wp_script_is( 'bp-livestamp', 'done' ) ) {
			return;
		}

	?>

		<script type="text/javascript">
			jq(function() {
				// Remove BP's livestamp. We handle context for English installs properly.
				jq('span.activity').livestamp('destroy');

				moment.updateLocale( 'en', {
					relativeTime : BP_Moment_i18n
				});
		        });

		</script>

	<?php
	}

	public function member_last_active( $retval ) {
		global $members_template;

		if ( is_numeric( $members_template->member->last_activity ) ) {
			$last_active = gmdate( 'Y-m-d H:i:s', $members_template->member->last_activity );
		} else {
			$last_active = $members_template->member->last_activity;
		}

		/*
		 * If we're here, that means 'populate_extras' is false, so manually fetch
		 * last activity.
		 */
		if ( empty( $last_active ) ) {
			$last_active = bp_get_user_last_activity( $members_template->member->id );
			if ( ! empty( $last_active ) ) {
				$members_template->member->last_activity = $activity;

			// Rare, but fallback to no activity.
			} else {
				return __( 'Never active', 'buddypress' );
			}
		}

		return sprintf(
			// ugh... inconsistencies!
			// groups component has the 'active' string separated from the timestamp,
			// while the members component has it all-in-one.  let's separate the two to
			// match the groups component
			__( 'active %s', 'buddypress' ),
			sprintf(
				'<span data-livestamp="%1$s">%2$s</span>',
				bp_core_get_iso8601_date( $last_active ),
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
				bp_core_get_iso8601_date( $members_template->member->user_registered ),
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
			bp_core_get_iso8601_date( $groups_template->group->date_created ),
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
			bp_core_get_iso8601_date( $last_active ),
			$retval
		);
	}

}