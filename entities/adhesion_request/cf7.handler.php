<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_action( 'tf_custom_admin_amapress_action_init_contact_form', 'tf_custom_admin_amapress_action_init_contact_form', 10, 3 );
function tf_custom_admin_amapress_action_init_contact_form( $page, $activeTab, $options ) {
	//TODO create default CF
}

add_filter( 'wpcf7_collect_mail_tags', 'amapress_wpcf7_collect_mail_tags' );
function amapress_wpcf7_collect_mail_tags( $mailtags ) {
	$mailtags[] = 'demande-titre';
	$mailtags[] = 'demande-href';
	$mailtags[] = 'lieux-noms';
	$mailtags[] = 'contrats-noms';

	return $mailtags;
}

add_action( 'wpcf7_before_send_mail', 'amapress_preinscription_handler' );
function amapress_preinscription_handler( WPCF7_ContactForm $cf7 ) {
	if ( intval( Amapress::getOption( 'preinscription-form' ) ) != $cf7->id() ) {
		return;
	}

	$submission   = WPCF7_Submission::get_instance();
	$first_name   = $submission->get_posted_data( 'prenom' );
	$last_name    = $submission->get_posted_data( 'nom' );
	$email        = $submission->get_posted_data( 'email' );
	$telephone    = $submission->get_posted_data( 'telephone' );
	$adresse      = $submission->get_posted_data( 'adresse' );
	$lieux        = $submission->get_posted_data( 'lieux' );
	$contrats     = $submission->get_posted_data( 'contrats' );
	$message      = $submission->get_posted_data( 'message' );
	$intermittent = $submission->get_posted_data( 'intermittent' );

	if ( empty( $lieux ) ) {
		$lieux = array();
	}
	$lieux_noms = array_map( function ( $lieu_id ) {
		$p = get_post( intval( $lieu_id ) );
		if ( empty( $p ) ) {
			return '';
		}

		return $p->post_title;
	}, $lieux );
	$lieux_noms = implode( ', ', $lieux_noms );

	if ( empty( $contrats ) ) {
		$contrats = array();
	}
	$contrats_noms = array_map( function ( $contrat_id ) {
		$p = get_post( intval( $contrat_id ) );
		if ( empty( $p ) ) {
			return '';
		}

		return $p->post_title;
	}, $contrats );
	if ( $intermittent ) {
		$contrats_noms[] = 'Intermittent';
	}
	$contrats_noms = implode( ', ', $contrats_noms );

	$my_post = array(
		'post_title'   => sprintf( 'Demande de préinscription de %s (%s) du %02d-%02d-%04d',
			$first_name . ' ' . $last_name,
			$email,
			date( 'd' ), date( 'm' ), date( 'Y' ) ),
		'post_type'    => 'amps_adh_req',
		'post_content' => $message,
		'post_status'  => 'publish',
		'meta_input'   => array(
			'amapress_adhesion_request_first_name'        => $first_name,
			'amapress_adhesion_request_last_name'         => $last_name,
			'amapress_adhesion_request_adresse'           => $adresse,
			'amapress_adhesion_request_telephone'         => $telephone,
			'amapress_adhesion_request_email'             => $email,
			'amapress_adhesion_request_lieux'             => $lieux,
			'amapress_adhesion_request_contrat_instances' => $contrats,
			'amapress_adhesion_request_intermittent'      => $intermittent,
			'amapress_adhesion_request_status'            => 'to_confirm',
		),
	);

	$post_id = wp_insert_post( $my_post );

	$mail = $cf7->prop( 'mail' );
	$mail = str_replace(
		array(
			'[demande-titre]',
			'[demande-href]',
			'[lieux-noms]',
			'[contrats-noms]'
		),
		array(
			$my_post['post_title'],
			admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			$lieux_noms,
			$contrats_noms
		),
		$mail
	);

	$cf7->set_properties( array(
		'mail' => $mail,
	) );
}