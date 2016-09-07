<?php

namespace Drupal\harbourmaster\Controller;

use Drupal\Core\Config\Config;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Exception\ClientException as HttpClientException;
use GuzzleHttp\ClientInterface as HttpClientInterface;

/**
 *
 */
class StatusPageController extends ControllerBase {

  /**
   * @var HttpClientInterface
   */
  protected $httpClient;

  protected $harbourmasterSettings;

  /**
   *
   */
  public function __construct(Config $harbourmaster_settings, HttpClientInterface $httpClient) {
    $this->httpClient = $httpClient;
    $this->harbourmasterSettings = $harbourmaster_settings;
  }

  /**
   *
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('harbourmaster.settings'),
      $container->get('http_client')
    );
  }

  /**
   *
   */
  public function status() {

    $harbourmasterApiUrl = $this->harbourmasterSettings->get('harbourmaster_api_url');

    $harbourmasterApiVersion = empty($this->harbourmasterSettings->get('harbourmaster_api_version')) ? 'v1' : $this->harbourmasterSettings->get('harbourmaster_api_version');
    $harbourmasterApiTenant = $this->harbourmasterSettings->get('harbourmaster_api_tenant');
    $userManagerUrl = $this->harbourmasterSettings->get('user_manager_url');

    $messages = [];

    // Connect to HMS API Server.
    if (empty($harbourmasterApiUrl)) {
      $messages['warning'][] = $this->t('HMS API Server: not configured');
    }
    else {
      try {
        $response = $this->httpClient->request('get', $harbourmasterApiUrl);
        if ($response->getBody() != '<h1>Harbourmaster</h1>') {
          $messages['error'][] = $this->t('HMS API Server: connect successful, but received wrong body');
        }
        else {
          $messages['status'][] = $this->t('HMS API Server: connect successful');
        }
      }
      catch (HttpClientException $e) {
        $messages['error'][] = $this->t('HMS API Server: could not connect (:message)', [':message' => $e->getMessage()]);
      }

      // Connect to HMS REST endpoint.
      if (empty($harbourmasterApiTenant)) {
        $messages['warning'][] = $this->t('HMS API REST Endpoint: not configured');
      }
      else {
        // HMS has no status endpoint, so we use /login and expect the correct error (as we send no credentials)
        try {
          $response = $this->httpClient->request('post', implode('/', [$harbourmasterApiUrl, $harbourmasterApiVersion, $harbourmasterApiTenant, 'login']));
          $messages['warning'][] = $this->t('HMS API REST Endpoint: connect successful, but unexpected status code (:code)', [':code' => $response->getStatusCode()]);
        }
        catch (HttpClientException $e) {
          if ($e->getCode() === 409) {
            $messages['status'][] = $this->t('HMS API REST Endpoint: connect successful');
          }
          else {
            $messages['error'][] = $this->t('HMS API REST Endpoint: could not connect (:message)', [':message' => $e->getMessage()]);
          }
        }
      }
    }

    // Connect to User Manager Endpoint.
    if (empty($userManagerUrl) || empty($harbourmasterApiTenant)) {
      $messages['warning'][] = $this->t('HMS User Manager: not configured');
    }
    else {
      try {
        $response = $this->httpClient->request('get', $userManagerUrl . '/' . $harbourmasterApiTenant);
        if ($response->getStatusCode() !== 200) {
          $messages['error'][] = $this->t('HMS User Manager: connect successful, but unexpected status code (:code)', [':code' => $response->getStatusCode()]);
        }
        else {
          $messages['status'][] = $this->t('HMS User Manager: connect successful');
        }
      }
      catch (HttpClientException $e) {
        $messages['error'][] = $this->t('HMS User Manager: could not connect (:message)', [':message' => $e->getMessage()]);
      }
    }

    // TODO check plausibility of HMS / User Manager / Cookie domains.
    return [
      '#theme' => 'status_messages',
      '#message_list' => $messages,
      '#status_headings' => [
        'status' => t('Status message'),
        'error' => t('Error message'),
        'warning' => t('Warning message'),
      ],
    ];

  }

}
