<?php


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Amapress_SudOuest_MailingList extends Amapress_MailingList {
	/** @return  Amapress_SudOuest_MailSystem */
	public function getSystem() {
		return parent::getSystem();
	}

	function __construct( $name, $info, Amapress_SudOuest_MailSystem $system ) {
		parent::__construct( $name, $info, $system );
	}

	public function getConfigurationLink() {
		return $this->getSystem()->getMailingListBaseUrl() . "edit_list_request/{$this->getName()}";
	}

	public function getMembersLink() {
		return $this->getSystem()->getMailingListBaseUrl() . "review/{$this->getName()}";
	}

	public function getBouncesLink() {
		return $this->getSystem()->getMailingListBaseUrl() . "reviewbouncing/{$this->getName()}";
	}


	public function getModerationLink() {
		return $this->getSystem()->getMailingListBaseUrl() . "modindex/{$this->getName()}";
	}

	public function setModerationMode( $value ) {
		parent::setModerationMode( $value );

		$this->getSystem()->setModeration( $value, $this->getName() );
	}

	public function setReplyTo( $value ) {
		parent::setReplyTo( $value );

		$this->getSystem()->setReplyTo( $value, $this->getName() );
	}

	public function setModerators( $value ) {
		$this->getSystem()->setModerators( $value, $this->getModeratorsEmails(), $this->getName() );
		parent::setModerators( $value );
	}

	/**
	 * @param Amapress_MailingListConfiguration $config
	 */
	public function syncMembers( $config ) {
		$moderators_queries = $config->getModeratorsQueries();
		if ( ! empty( $moderators_queries ) ) {
			$user_ids = array();
			foreach ( $moderators_queries as $q ) {
				$users = get_users( $q );
				if ( ! empty( $users ) ) {
					$user_ids = array_merge( $user_ids, array_map( function ( $u ) {
						return $u->ID;
					}, $users ) );
				}
			}
			$this->setModerators( $user_ids );
		}

		$this->getSystem()->setRemoteUrl( $this->getName(), $this->getRemoteUrl( $config->ID ) );
	}

	public function getRemoteUrl( $id ) {
		return add_query_arg(
			array(
				'action' => 'fetch-mailing-members',
				'id'     => $id,
				'secret' => Amapress::getOption( 'sud-ouest_secret' ),
			),
			admin_url( 'admin-post.php', 'http' )
		);
	}

	/**
	 * @param Amapress_MailingListConfiguration $config
	 *
	 * @return string
	 */
	public function isSync( $config ) {
		$moderators_queries = $config->getModeratorsQueries();
		if ( ! empty( $moderators_queries ) ) {
			$user_emails = array();
			foreach ( $moderators_queries as $q ) {
				$users = get_users( $q );
				if ( ! empty( $users ) ) {
					foreach ( $users as $user ) {
						$amapien     = AmapressUser::getBy( $user );
						$user_emails = array_merge( $user_emails, $amapien->getAllEmails() );
					}
					$user_emails = array_unique( $user_emails );
				}
			}
			$inter = array_intersect( $this->getModeratorsEmails(), $user_emails );
			if ( count( $user_emails ) != count( $inter ) || count( $this->getModeratorsEmails() ) != count( $inter ) ) {
				return 'not_sync';
			}
		}
		$remote_url = isset( $this->info['remote_url'] ) ? $this->info['remote_url'] : '';
		if ( ! empty( $remote_url ) ) {
			$new_remote_url = $this->getRemoteUrl( $config->ID );
			if ( $new_remote_url == $remote_url ) {
				return 'sync';
			} else {
				return 'not_sync';
			}
		} else {
			return 'manual';
		}
	}

	public
	function getModerationControl() {

	}

	public
	function distributeMail(
		$msg_id
	) {
		return $this->getSystem()->distributeMail( $this->getName(), $msg_id );
	}

	public
	function rejectMailQuiet(
		$msg_id
	) {
		return $this->getSystem()->rejectMailQuiet( $this->getName(), $msg_id );
	}

	public
	function rejectMail(
		$msg_id
	) {
		return $this->getSystem()->rejectMail( $this->getName(), $msg_id );
	}

	/** @return Amapress_MailingList_Mail[] */
	public
	function getMailWaitingModeration() {
		if ( ! isset( $this->info['messages'] ) ) {
			if ( $this->getMailWaitingModerationCount() > 0 ) {
				$this->info['messages'] = $this->getSystem()->getMailWaitingModeration( $this->getName() );
			} else {
				$this->info['messages'] = array();
			}
		}

		return $this->info['messages'];
	}

	public
	function getFullName() {
		return $this->getSystem()->getFullName( $this->getName() );
	}

	public
	function getId() {
		return $this->getSystem()->getId( $this->getName() );
	}
}

class Amapress_SudOuest_MailSystem extends Amapress_MailingSystem {
	public function handleMessagesModeration() {
		return true;
	}

	static function parseSelect( $html_content, $select_name, &$options ) {
		preg_match( '%\<select\s+name\="' . $select_name . '".+?\<\/select\>%s', $html_content, $m );
		$options_matches = array();
		preg_match_all( '%\<option\s+value\="(?<value>[^"]+)"(?<selected>\s+selected\="selected")?[^\>]*\>(?<text>[^\<]+)\<\/option\>%s', $m[0], $options_matches, PREG_SET_ORDER );
		$options  = array();
		$selected = null;
		foreach ( $options_matches as $opt ) {
			if ( ! empty( $opt['selected'] ) ) {
				$selected = html_entity_decode( $opt['value'] );
			}
			$options[ html_entity_decode( $opt['value'] ) ] = html_entity_decode( $opt['text'] );
		}

		return $selected;
	}

	/** @var  \GuzzleHttp\Client $client */
	private static $client;
	private $mailinglist_domain;

	public function getMailingListBaseUrl() {
		return "https://{$this->mailinglist_domain}/wws/";
	}

	public function getCreationLink() {
		return $this->getMailingListBaseUrl() . 'create_list_request';
	}


	public function getFullName( $name ) {
		return "$name@{$this->mailinglist_domain}";
	}

	public function getId( $name ) {
		return "sud-ouest:{$this->getFullName($name)}";
	}

	public function getMailingList( $name ) {
		$list_info = array();
		$resp      = self::$client->get( "/wws/edit_list_request/$name/description" );
		if ( 200 == $resp->getStatusCode() ) {
			$body = $resp->getBody();
			preg_match( '/id\="single_param.subject.name"\s+value="(?<desc>[^"]+)"/', $body, $m );
			$list_info['desc'] = ( $m['desc'] );
			preg_match( '/Emails?\s*\((?<waiting>\d+)\)/', $body, $m );
			$list_info['waiting'] = isset( $m['waiting'] ) ? intval( $m['waiting'] ) : 0;
			preg_match( '/Abonnés\s*:\s*(?:\<span\>)?(?<members_count>\d+)/', $body, $m );
			$list_info['members_count'] = isset( $m['members_count'] ) ? intval( $m['members_count'] ) : 0;
			preg_match( '/Taux\s*d\'erreurs\s*:\s*(?:\<span\>)?(?<bounce_rate>\d+(?:,\d+)?)/', $body, $m );
			$list_info['bounce_rate'] = isset( $m['bounce_rate'] ) ? $m['bounce_rate'] : 0;
			preg_match_all( '/id\="single_param.(?:owner|moderator).\d+.email"\s+value="(?<mod>[^"]+)"/', $body, $m, PREG_SET_ORDER );
			$list_info['moderators']        = array();
			$list_info['moderators_emails'] = array();
			foreach ( $m as $mm ) {
				$email = html_entity_decode( $mm['mod'] );
				$u     = get_user_by( 'email', $email );
				if ( $u ) {
					$list_info['moderators'][]        = $u->ID;
					$list_info['moderators_emails'][] = $email;
				}
			}
			$list_info['moderators']        = array_unique( $list_info['moderators'] );
			$list_info['moderators_emails'] = array_unique( $list_info['moderators_emails'] );
		}
		$resp = self::$client->get( "/wws/edit_list_request/$name/sending" );
		if ( 200 == $resp->getStatusCode() ) {
			$body                          = $resp->getBody();
			$reply_to_options              = array();
			$list_info['reply_to']         = self::parseSelect( $body, 'single_param.reply_to_header.value', $reply_to_options );
			$list_info['reply_to_options'] = $reply_to_options;
			$moderations                   = array();
			$list_info['moderation']       = self::parseSelect( $body, 'single_param.send.name', $moderations );
			$list_info['moderations']      = $moderations;
		}
		$resp = self::$client->get( "/wws/edit_list_request/$name/data_source" );
		if ( 200 == $resp->getStatusCode() ) {
			$body = $resp->getBody();

			preg_match( '/id\="single_param.include_remote_file.0.url"\s+value="(?<url>[^"]*)"/', $body, $m );
			$list_info['remote_url'] = html_entity_decode( $m['url'] );
		}

		return new Amapress_SudOuest_MailingList( $name, $list_info, $this );
	}

	protected function fetchMails() {
		if ( $this->error_message !== false ) {
			return array();
		}

		$ret = array();

		$resp = self::$client->get( '/wws/lists' );
		if ( 200 == $resp->getStatusCode() ) {
			$body  = $resp->getBody();
			$lists = array();
			preg_match_all( '%\<li\s+class\="listenum"\>.+?\<\/li\>%s', $body, $lists, PREG_SET_ORDER );
			foreach ( $lists as $list ) {
				preg_match( '/href\="\/wws\/+info\/(?<name>[^"]+)"/', $list[0], $m );
				$ret[] = $this->getMailingList( $m['name'] );
			}
		}

		return $ret;
	}

	function __construct( $mailinglist_domain, $login, $pass ) {
		parent::__construct();
		require_once AMAPRESS__PLUGIN_DIR . 'vendor/autoload.php';

		$this->mailinglist_domain = $mailinglist_domain;

		$this->ensureConnected( $login, $pass );
	}

	function ensureConnected( $login, $pass ) {
		if ( self::$client == null ) {
			$cookies = new \GuzzleHttp\Cookie\CookieJar();
			//$jar->add
			self::$client = new \GuzzleHttp\Client(
				array(
					'base_uri' => "https://{$this->mailinglist_domain}",
					'cookies'  => $cookies,
				) );
			$resp         = self::$client->post( '/wws',
				[
					'form_params' =>
						array(
							'action'       => 'login',
							'action_login' => 'Login',
							'email'        => $login,
							'passwd'       => $pass,
						)
				]
			);
			if ( 200 == $resp->getStatusCode() ) {
				$this->error_message = false;
			} else {
				$this->error_message = $resp->getReasonPhrase();
			}
		}
	}

	public function setRemoteUrl( $list_name, $remote_url ) {
		$resp = self::$client->get( "/wws/edit_list_request/$list_name/data_source" );
		$body = $resp->getBody();
		preg_match( '/type="hidden" name="serial" value="(?<serial>\d+)"/', $body, $m );

//        var_dump($sql_query);
		$post_data = array(
			'serial'                                  => $m['serial'],
			'single_param.include_remote_file.0.name' => 'Amapress',
			'single_param.include_remote_file.0.url'  => $remote_url,
			'list'                                    => $list_name,
			'group'                                   => 'data_source',
			'action'                                  => 'edit_list',
			'action_edit_list'                        => 'Mise à jour',
		);
		$resp      = self::$client->post( '/wws', [
			'form_params' => $post_data
		] );
		$body      = $resp->getBody();

		return strpos( $body, 'La configuration de la liste a été mise à jour' ) !== false;
	}

	public function setModeration( $moderation, $list_name ) {
		$resp = self::$client->get( "/wws/edit_list_request/$list_name/sending" );
		$body = $resp->getBody();
		preg_match( '/type="hidden" name="serial" value="(?<serial>\d+)"/', $body, $m );

//        var_dump($sql_query);
		$post_data = array(
			'serial'                 => $m['serial'],
			'single_param.send.name' => $moderation,
			'list'                   => $list_name,
			'group'                  => 'sending',
			'action'                 => 'edit_list',
			'action_edit_list'       => 'Mise à jour',
		);
		$resp      = self::$client->post( '/wws', [
			'form_params' => $post_data
		] );
		$body      = $resp->getBody();

		return strpos( $body, 'La configuration de la liste a été mise à jour' ) !== false;
	}

	public function setReplyTo( $reply_to, $list_name ) {
		$resp = self::$client->get( "/wws/edit_list_request/$list_name/sending" );
		$body = $resp->getBody();
		preg_match( '/type="hidden" name="serial" value="(?<serial>\d+)"/', $body, $m );

		$post_data = array(
			'serial'                             => $m['serial'],
			'single_param.reply_to_header.value' => $reply_to,
			'list'                               => $list_name,
			'group'                              => 'sending',
			'action'                             => 'edit_list',
			'action_edit_list'                   => 'Mise à jour',
		);
		$resp      = self::$client->post( '/wws', [
			'form_params' => $post_data
		] );
		$body      = $resp->getBody();

		return strpos( $body, 'La configuration de la liste a été mise à jour' ) !== false;
	}

	public function setModerators( $new_moderators, $old_moderators_emails, $list_name ) {
		$resp = self::$client->get( "/wws/edit_list_request/$list_name/description" );
		$body = $resp->getBody();
		preg_match( '/type="hidden" name="serial" value="(?<serial>\d+)"/', $body, $m );

		$post_data = array(
			'serial'           => $m['serial'],
			'list'             => $list_name,
			'group'            => 'description',
			'action'           => 'edit_list',
			'action_edit_list' => 'Mise à jour',
		);

		$j = 0;
		for ( $i = 0; $i < count( $new_moderators ); $i ++ ) {
			$mail = amapress_get_user_by_id_or_archived( $new_moderators[ $i ] );
			if ( $mail ) {
				$amapien = AmapressUser::getBy( $mail );
				foreach ( $amapien->getAllEmails() as $email ) {
					$post_data["single_param.owner.$j.email"]     = $email;
					$post_data["single_param.owner.$j.gecos"]     = '';
					$post_data["single_param.owner.$j.info"]      = '';
					$post_data["single_param.owner.$j.profile"]   = 'privileged';
					$post_data["single_param.owner.$j.reception"] = 'mail';
					$j ++;
				}
			}
		}
		while ( $j < count( $old_moderators_emails ) ) {
			$post_data["single_param.owner.$j.email"]     = '';
			$post_data["single_param.owner.$j.gecos"]     = '';
			$post_data["single_param.owner.$j.info"]      = '';
			$post_data["single_param.owner.$j.profile"]   = 'privileged';
			$post_data["single_param.owner.$j.reception"] = 'mail';
			$j ++;
		}

//        var_dump($sql_query);

		$resp = self::$client->post( '/wws', [
			'form_params' => $post_data
		] );
		$body = $resp->getBody();

		return strpos( $body, 'La configuration de la liste a été mise à jour' ) !== false;
	}

	public function distributeMail( $list_name, $msg_id ) {
		$resp = self::$client->post( '/wws', [
			'form_params' => array(
				'list'              => $list_name,
				'id'                => $msg_id,
				'action_distribute' => 'Distribuer',
			)
		] );

		return 200 == $resp->getStatusCode();
	}

	public function rejectMailQuiet( $list_name, $msg_id ) {
		$resp = self::$client->post( '/wws', [
			'form_params' =>
				array(
					'list'                => $list_name,
					'id'                  => $msg_id,
					'action_reject.quiet' => 'Rejeter sans prévenir l\'auteur',
				)
		] );

		return 200 == $resp->getStatusCode();
	}

	public function rejectMail( $list_name, $msg_id ) {
		$resp = self::$client->post( '/wws', [
			'form_params' =>
				array(
					'list'          => $list_name,
					'id'            => $msg_id,
					'action_reject' => 'Rejeter',
				)
		] );

		return 200 == $resp->getStatusCode();
	}

	/** @return Amapress_MailingList_Mail[] */
	public function getMailWaitingModeration( $name ) {
		if ( Amapress::getOption( 'sud-ouest_manage_waiting' ) != true ) {
			return [];
		}

		$resp = self::$client->get( "/wws/modindex/$name" );
		if ( 200 == $resp->getStatusCode() ) {
			$body = $resp->getBody();

			$message_matches = array();
			preg_match_all( '%href\="\/wws\/viewmod\/' . $name . '\/(?<msg_id>[^"]+)"%s', $body, $message_matches, PREG_SET_ORDER );

			$messages = array();
			foreach ( $message_matches as $msg ) {
				$msg_id = $msg['msg_id'];
				$resp   = self::$client->get( "/wws/viewmod/$name/$msg_id" );
				if ( 200 == $resp->getStatusCode() ) {
					$body = $resp->getBody();

					$headers_matches = array();
					preg_match_all( '%\<li\>\<strong>(?<id>[^\<]+)\<\/strong\>:\s*(?<content>[^\<]+)\<\/li\>%s', $body, $headers_matches, PREG_SET_ORDER );

					$headers = array();
					foreach ( $headers_matches as $h ) {
						$headers[ $h['id'] ] = $h['content'];
					}

					preg_match( '/\<\!--X-Body-of-Message--\>(?<body>.+?)\<\!--X-Body-of-Message-End--\>/s', $body, $content );
					$messages[] = new Amapress_MailingList_Mail( $msg_id,
						array(
							'id'      => $msg_id,
							'headers' => $headers,
							'content' => html_entity_decode( $content['body'] ),
						) );
				}
			}

			return $messages;
		}

		return array();
	}
}

add_filter( 'amapress_get_mailinglist_systems', 'amapress_SudOuest_get_mailinglist_systems' );
function amapress_SudOuest_get_mailinglist_systems( $systems ) {
	$mailinglist_domain = trim( trim( Amapress::getOption( 'sud-ouest_mailing_domain' ) ), '@' );
	$login              = Amapress::getOption( 'sud-ouest_admin_user' );
	$pass               = Amapress::getOption( 'sud-ouest_admin_pass' );
	if ( ! empty( $mailinglist_domain ) && ! empty( $login ) && ! empty( $pass ) ) {
		$systems[] = new Amapress_SudOuest_MailSystem( $mailinglist_domain, $login, $pass );
	}

	return $systems;
}

add_action( 'admin_post_nopriv_fetch-mailing-members', function () {
//	file_put_contents(AMAPRESS__PLUGIN_DIR."/test.txt", var_export(getallheaders() , true));
	if ( empty( Amapress::getOption( 'sud-ouest_admin_user' ) ) ) {
		wp_die( "No Sud-Ouest Mailing list found" );
	}

	if ( ! isset( $_REQUEST['secret'] ) || $_REQUEST['secret'] != Amapress::getOption( 'sud-ouest_secret' ) ) {
		wp_die( "Sync secret does not match" );
	}

	$ml = new Amapress_MailingListConfiguration( $_REQUEST['id'] );
	if ( ! $ml ) {
		wp_die( "Mailing list {$_REQUEST['id']} cannot be found" );
	}

	header( 'Content-type: text/plain' );
	global $wpdb;
	foreach ( $wpdb->get_col( Amapress_MailingList::getSqlQuery( $ml->getMembersQueries() ) ) as $email ) {
		echo "{$email}\n";
	}
	die();
} );