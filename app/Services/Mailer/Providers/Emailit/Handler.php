<?php

namespace FluentMail\App\Services\Mailer\Providers\Emailit;

use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\Includes\Support\Arr;

class Handler extends BaseHandler {
	use ValidatorTrait;

	protected $url = 'https://api.emailit.com/v2/emails';

	public function send() {
		if ( $this->preSend() ) {
			return $this->postSend();
		}
		return $this->handleResponse( new \WP_Error( 422, __( 'Something went wrong!', 'fluent-smtp' ) ) );
	}

	public function postSend() {
		$body = array(
			'from'    => $this->formatAddress( $this->getParam( 'sender_email' ), $this->getParam( 'sender_name' ) ),
			'to'      => $this->formatRecipients( $this->getParam( 'to', array() ) ),
			'subject' => $this->getSubject(),
		);

		if ( 'text/plain' === $this->getParam( 'headers.content-type' ) ) {
			$body['text'] = $this->getParam( 'message' );
		} else {
			$body['html'] = $this->getParam( 'message' );
			if ( $this->phpMailer->AltBody ) {
				$body['text'] = $this->phpMailer->AltBody;
			}
		}

		foreach ( array( 'cc', 'bcc' ) as $type ) {
			$recipients = $this->formatRecipients( $this->getParam( 'headers.' . $type, array() ) );
			if ( $recipients ) {
				$body[ $type ] = $recipients;
			}
		}

		$reply_to = $this->formatRecipients( $this->getParam( 'headers.reply-to', array() ) );
		if ( $reply_to ) {
			$body['reply_to'] = $reply_to;
		}

		$attachments = $this->getAttachments();
		if ( $attachments ) {
			$body['attachments'] = $attachments;
		}

		$response = wp_safe_remote_post(
			$this->url,
			array_merge(
				$this->getDefaultParams(),
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $this->getSetting( 'api_key' ),
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
				)
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->handleResponse( $response );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 200 && $code < 300 ) {
			return $this->handleResponse(
				array(
					'email_id'   => Arr::get( $data, 'id' ),
					'message_id' => Arr::get( $data, 'message_id' ),
					'status'     => Arr::get( $data, 'status' ),
				)
			);
		}

		$message = Arr::get( $data, 'details', Arr::get( $data, 'error', __( 'Emailit returned an unknown error.', 'fluent-smtp' ) ) );
		return $this->handleResponse( new \WP_Error( $code ?: 400, $message, $data ) );
	}

	private function formatAddress( $email, $name = '' ) {
		return $name ? $name . ' <' . $email . '>' : $email;
	}

	private function formatRecipients( $recipients ) {
		return array_values(
			array_map(
				function ( $recipient ) {
					return $this->formatAddress( $recipient['email'] ?? '', $recipient['name'] ?? '' );
				},
				array_filter( (array) $recipients )
			)
		);
	}

	private function getAttachments() {
		$attachments = array();
		foreach ( (array) $this->getParam( 'attachments', array() ) as $attachment ) {
			$path = $attachment[0] ?? '';
			if ( ! $path || ! is_readable( $path ) ) {
				continue;
			}
			$attachments[] = array(
				'filename'     => basename( $path ),
				'content'      => base64_encode( file_get_contents( $path ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				'content_type' => mime_content_type( $path ),
			);
		}
		return $attachments;
	}

	public function setSettings( $settings ) {
		if ( 'wp_config' === ( $settings['key_store'] ?? 'db' ) ) {
			$settings['api_key'] = defined( 'FLUENTMAIL_EMAILIT_API_KEY' ) ? FLUENTMAIL_EMAILIT_API_KEY : '';
		}
		return parent::setSettings( $settings );
	}
}
