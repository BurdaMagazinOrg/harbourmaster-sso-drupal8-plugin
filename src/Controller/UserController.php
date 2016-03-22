<?php

/**
 * Copyright © 2016 Valiton GmbH.
 *
 * This file is part of msg-web.
 *
 * msg-web is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * msg-web is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with msg-web.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Drupal\hms\Controller;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\user\Controller\UserController as DrupalUserController;
use Drupal\user\UserDataInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\hms\User\Manager as HmsUserManager;

class UserController extends DrupalUserController {

  /**
   * @var HmsUserManager
   */
  protected $hmsUserManager;


  /**
   * Constructs a UserController object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   * @param \Drupal\hms\User\Manager $hmsUserManager
   */
  public function __construct(DateFormatterInterface $date_formatter, UserStorageInterface $user_storage, UserDataInterface $user_data, HmsUserManager $hmsUserManager) {
    $this->hmsUserManager = $hmsUserManager;
    parent::__construct($date_formatter, $user_storage, $user_data);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('entity.manager')->getStorage('user'),
      $container->get('user.data'),
      $container->get('hms.user_manager')
    );
  }

  public function userPage() {
    if ($this->currentUser()->isAuthenticated() && (NULL !== $this->hmsUserManager->findHmsUserKeyForUid($this->currentUser()->id()))) {
      return [
        '#theme' => 'usermanager.profile_page',
      ];
    }
    // TODO will we have a separate profile for HMS users?
    return parent::userPage(); // TODO: Change the autogenerated stub
  }

  public function logout() {
    if ($this->currentUser()->isAuthenticated() && (NULL !== $this->hmsUserManager->findHmsUserKeyForUid($this->currentUser()->id()))) {
      $userManagerUrl = $this->config('hms.settings')->get('user_manager_url');
      // TODO why is this considered a "weak" route?
      $queryString = http_build_query([
        'logout_redirect' => Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString(TRUE)->getGeneratedUrl(),
      ]);

      return new TrustedRedirectResponse(
        $userManagerUrl . '/signout?' . $queryString
      );
    }
    return parent::logout();
  }

  public function hmsLoginPage() {
    if ($this->currentUser()->isAuthenticated()) {
//      drupal_set_message(t('You have been logged in.'));
      return $this->redirect('<front>');
    }
    return [
      '#theme' => 'usermanager.login_page',
    ];
  }

}

