<?php
/**
 * SOPAC is The Social OPAC: a Drupal module that serves as a wholly integrated web OPAC for the Drupal CMS
 * This file contains the Drupal include functions for all the SOPAC admin pieces and configuration options
 * This file is called via hook_user
 *
 * @package SOPAC
 * @version 2.1
 * @author John Blyberg
 */

/**
 * This is a sub-function of the hook_user "view" operation.
 */
function sopac_user_view( $op, &$edit, &$account, $category = NULL ) {
  $locum = sopac_get_locum();
  // SOPAC uses the first 7 characters of the MD5 hash instead of caching the user's password
  // like it used to do.  It's more secure this way, IMHO.
  $account->locum_pass = substr( $account->pass, 0, 7 );

  // Patron information table (top of the page)
  $patron_details_table = sopac_user_info_table( $account, $locum );
  if ( variable_get( 'sopac_summary_enable', 1 ) ) {
    $result['patroninfo']['#title'] = t( 'Account Summary' );
    $result['patroninfo']['#weight'] = 1;
    $result['patroninfo']['#type'] = 'user_profile_category';
    $result['patroninfo']['details']['#value'] = $patron_details_table;
  }

  // Patron checkouts (middle of the page)
  if ( $account->valid_card && $account->bcode_verify ) {
    $co_table = sopac_user_chkout_table( $account, $locum );
    if ( $co_table ) {
      $result['patronco']['#title'] = t( 'Checked-out Items' );
      $result['patronco']['#weight'] = 2;
      $result['patronco']['#type'] = 'user_profile_category';
      $result['patronco']['details']['#value'] = $co_table;
    }
  }

  // Patron holds (bottom of the page)
  if ( $account->valid_card && $account->bcode_verify ) {
    $holds_table = drupal_get_form( 'sopac_user_holds_form', $account );
    if ( $holds_table ) {
      $result['patronholds']['#title'] = t( 'Requested Items' );
      $result['patronholds']['#weight'] = 3;
      $result['patronholds']['#type'] = 'user_profile_category';
      $result['patronholds']['details']['#value'] = $holds_table;
    }
  }

  // Commit the page content
  $account->content = array_merge( $account->content, $result );

  // The Summary is not really needed.
  if ( variable_get( 'sopac_history_hide', 1 ) ) {
    unset( $account->content['summary'] );
  }
  unset( $account->content['Preferences'] );
}

/**
 * Returns a Drupal themed table of patron information for the "My Account" page.
 *
 * @param object  $account Drupal user object for account being viewed
 * @param object  $locum   Instansiated Locum object
 * @return string Drupal themed table
 */
function sopac_user_info_table( &$account, &$locum ) {

  $rows = array();

  // create home branch link if appropriate
  if ( $account->profile_pref_home_branch ) {
    $home_branch_link = l( $account->profile_pref_home_branch, 'user/' . $account->uid . '/edit/Preferences' );
  }
  elseif ( variable_get( 'sopac_home_selector_options', FALSE ) ) {
    $home_branch_link = l( t( 'Click to select your home branch' ), 'user/' . $account->uid . '/edit/Preferences' );
  }
  else {
    $home_branch_link = NULL;
  }

  if ( $account->profile_pref_cardnum ) {
    $cardnum = preg_replace('/\s+/', '', $account->profile_pref_cardnum);
    if ( $cardnum ) { $userinfo = $locum->get_patron_info( $cardnum ); }
    if ( $userinfo['cardnum'] ) {
      db_query( "DELETE FROM {sopac_card_verify}  WHERE uid = %d AND pnum = 0", $account->uid);
      $bcode_verify = sopac_bcode_isverified( $account );
      $pnum = $userinfo['pnum'];
      $db_obj = db_fetch_object( db_query( "SELECT pnum FROM {sopac_card_verify} WHERE uid = %d AND cardnum = '%s' AND verified > 0", $account->uid, $cardnum ) );
      $drupal_pnum = $db_obj->pnum;
      if ( $pnum != $drupal_pnum ) {
        db_query( "UPDATE {sopac_card_verify} SET pnum = '%s' WHERE uid = %d AND cardnum = '%s'", $pnum, $account->uid, $cardnum );
      }
      if ( $pnum ) {
        $pnum_userinfo = $locum->get_patron_info( $pnum );
        if ( $pnum_userinfo['cardnum'] != $cardnum ) {
          $edit = array( 'profile_pref_cardnum' => $pnum_userinfo['cardnum'] );
          user_save( $account, $edit, 'Preferences' );
          db_query( "UPDATE {sopac_card_verify} SET cardnum = '%s' WHERE uid = %d AND pnum = '%s'", $pnum_userinfo['cardnum'], $account->uid, $pnum );
          $cardnum = $pnum_userinfo['cardnum'];
        }
      }
    } else {
      $db_obj = db_fetch_object( db_query( "SELECT pnum FROM {sopac_card_verify} WHERE uid = %d AND cardnum = '%s'", $account->uid, $cardnum ) );
      if ( $db_obj->pnum ) {
        $pnum = $db_obj->pnum;
        $userinfo = $locum->get_patron_info( $pnum );
        if ( $userinfo['cardnum'] ) {
          $account->profile_pref_cardnum = $userinfo['cardnum'];
          $cardnum = $account->profile_pref_cardnum;
          $edit = array( 'profile_pref_cardnum' => $cardnum );
          user_save( $account, $edit, 'Preferences' );
          db_query( "UPDATE {sopac_card_verify} SET cardnum = '%s' WHERE uid = %d AND pnum = '%s'", $cardnum, $account->uid, $pnum );
          $bcode_verify = sopac_bcode_isverified( $account );
        } else {
          $edit = array( 'profile_pref_cardnum' => NULL );
          user_save( $account, $edit, 'Preferences' );
          db_query( "DELETE FROM {sopac_card_verify} WHERE uid = %d AND pnum = '%s'", $account->uid, $pnum );
          unset( $cardnum );
          unset( $pnum );
          unset( $userinfo );
        }
      } else {
        $edit = array( 'profile_pref_cardnum' => NULL );
        user_save( $account, $edit, 'Preferences' );
        db_query( "DELETE FROM {sopac_card_verify} WHERE uid = %d AND cardnum = '%s'", $account->uid, $cardnum );
        unset( $cardnum );
        unset( $userinfo );
      }
    }
  }

  if ( $cardnum && $pnum ) {
    $bcode_verify = sopac_bcode_isverified( $account );

    if ( $bcode_verify ) {
      $account->bcode_verify = TRUE;
    }
    else {
      $account->bcode_verify = FALSE;
    }
    if ( $userinfo['pnum'] ) {
      $account->valid_card = TRUE;
    }
    else {
      $account->valid_card = FALSE;
    }

    // Construct the user details table based on what is configured in the admin interface
    if ( $account->valid_card && $bcode_verify ) {
      if ( variable_get( 'sopac_pname_enable', 1 ) ) {
        $rows[] = array( array( 'data' => t( 'Patron Name' ), 'class' => 'attr_name' ), $userinfo['name'] );
      }
      if ( variable_get( 'sopac_lcard_enable', 1 ) ) {
        $cardnum_link = l( $cardnum, 'user/' . $account->uid . '/edit/Preferences' );
        $rows[] = array( array( 'data' => t( 'Library Card Number' ), 'class' => 'attr_name' ), $cardnum_link );
      }
      // Add row for home branch if appropriate
      if ( $home_branch_link ) {
        $rows[] = array( array( 'data' => t( 'Home Branch' ), 'class' => 'attr_name' ), $home_branch_link );
      }
      // Checkout history, if it's turned on
      if ( variable_get( 'sopac_checkout_history_enable', 0 ) ) {
        $cohist_enabled = $account->profile_pref_cohist ? 'Enabled' : 'Disabled';
        if ($cardnum) { $ils_hist_enabled = $locum->set_patron_checkout_history( $cardnum, $locum_pass, 'status' ); }
        if ( $cohist_enabled == 'Enabled' ) {
          // TODO Check ILS, enable it if it's not (w/ cache check)
          if (!$ils_hist_enabled && $cardnum) {
            $locum->set_patron_checkout_history( $cardnum, $locum_pass, 'on' );
          }
          // Grab + update newest checkouts if not checked in 24 hours.
        }
        else {
          if ($ils_hist_enabled && $cardnum) {
            $locum->set_patron_checkout_history( $cardnum, $locum_pass, 'off' );
          }
        }
        $rows[] = array( array( 'data' => t( 'Checkout History' ), 'class' => 'attr_name' ), l( $cohist_enabled, 'user/' . $account->uid . '/edit/Preferences' ) );
      }
      if ( variable_get( 'sopac_numco_enable', 1 ) ) {
        $rows[] = array( array( 'data' => t( 'Items Checked Out' ), 'class' => 'attr_name' ), $userinfo['checkouts'] );
      }
      if ( variable_get( 'sopac_fines_display', 1 ) && variable_get( 'sopac_fines_enable', 1 ) ) {
        $amount_link = l( '$' . number_format( $userinfo['balance'], 2, '.', '' ), 'https://payments.darienlibrary.org/eCommerceWebModule/Home' );
        $rows[] = array( array( 'data' => t( 'Fine Balance' ), 'class' => 'attr_name' ), $amount_link );
      }
      if ( variable_get( 'sopac_cardexp_enable', 1 ) ) {
        $rows[] = array( array( 'data' => t( 'Card Expiration Date' ), 'class' => 'attr_name' ), date( 'm-d-Y', $userinfo['expires'] ) );
      }
      if ( variable_get( 'sopac_tel_enable', 1 ) ) {
        $rows[] = array( array( 'data' => t( 'Telephone' ), 'class' => 'attr_name' ), $userinfo['tel1'] );
      }
    }
    else {
      $rows[] = array( array( 'data' => t( 'Library Card Number' ), 'class' => 'attr_name' ), $cardnum_link );
    }
  }
  else {
    $cardnum_link = l( t( 'Click to add your library card' ), 'user/' . $account->uid . '/edit/Preferences' );
    $rows[] = array( array( 'data' => t( 'Library Card Number' ), 'class' => 'attr_name' ), $cardnum_link );
    // add row for home branch if appropriate
    if ( $home_branch_link ) {
      $rows[] = array( array( 'data' => t( 'Home Branch' ), 'class' => 'attr_name' ), $home_branch_link );
    }
  }

  if ( $account->mail && variable_get( 'sopac_email_enable', 1 ) ) {
    $rows[] = array( array( 'data' => t( 'Email' ), 'class' => 'attr_name' ), $account->mail );
  }

  // Begin creating the user information display content
  $user_info_disp = theme( 'table', NULL, $rows, array( 'id' => 'patroninfo-summary', 'cellspacing' => '0' ) );

  if ( $account->valid_card && !$bcode_verify ) {
    $user_info_disp .= '<div class="error">' . variable_get( 'sopac_uv_cardnum', t( 'The card number you have provided has not yet been verified by you.  In order to make sure that you are the rightful owner of this library card number, we need to ask you some simple questions.' ) ) . '</div>' . drupal_get_form( 'sopac_bcode_verify_form', $account->uid, $cardnum );
  }
  elseif ( $cardnum && !$account->valid_card ) {
    $user_info_disp .= '<div class="error">' . variable_get( 'sopac_invalid_cardnum', t( 'It appears that the library card number stored on our website is invalid. If you have received a new card, or feel that this is an error, please click on the card number above to change it to your most recent library card. If you need further help, please contact us.' ) ) . '</div>';
  }

  return $user_info_disp;
}

/**
 * Returns a Drupal-themed table of checked-out items as well as the renewal form functionality.
 *
 * @param object  $account Drupal user object for account being viewed
 * @param object  $locum   Instansiated Locum object
 * @return string Drupal themed table
 */
function sopac_user_chkout_table( &$account, &$locum, $max_disp = NULL ) {

  // Process any renew requests that have been submitted
  if ( $_POST['sub_type'] == 'Renew Selected' ) {
    if ( count( $_POST['inum'] ) ) {
      foreach ( $_POST['inum'] as $inum => $varname ) {
        $items[$inum] = $varname;
      }
      $renew_status = $locum->renew_items( $account->profile_pref_cardnum, $account->locum_pass, $items );
    }
  }
  elseif ( $_POST['sub_type'] == 'Renew All' ) {
    $renew_status = $locum->renew_items( $account->profile_pref_cardnum, $account->locum_pass, 'all' );
  }

  // Create the check-outs table
  $rows = array();
  if ( $account->profile_pref_cardnum ) {
    $locum_pass = substr( $account->pass, 0, 7 );
    $cardnum = $account->profile_pref_cardnum;
    $checkouts = $locum->get_patron_checkouts( $cardnum, $locum_pass );

    if ( !count( $checkouts ) ) {
      return t( 'No items checked out.' );
    }
    $header = array( '', t( 'Title' ), t( 'Due Date&nbsp;&nbsp;&nbsp;&nbsp; ' ) );
    foreach ( $checkouts as $co ) {
      if ( $renew_status[$co['inum']]['error'] ) {
        $duedate = '<span style="color: red;">' . $renew_status[$co['inum']]['error'] . '</span>';
      }
      else {
        if ( time() > $co['duedate'] ) {
          $duedate = '<span style="color: red;">' . date( 'm-d-Y', $co['duedate'] ) . '</span>';
        }
        else {
          $duedate = date( 'm-d-Y', $co['duedate'] );
        }
      }

      $rows[] = array(
        '<input type="checkbox" name="inum[' . $co['inum'] . ']" value="' . $co['varname'] . '">',
        l( $co['title'], 'catalog/record/' . $co['bnum'] ),
        $duedate,
      );
    }
    $submit_buttons = '<input type="submit" name="sub_type" value="' . t( 'Renew Selected' ) . '"> <input type="submit" name="sub_type" value="' . t( 'Renew All' ) . '">';
    $rows[] = array( 'data' => array( array( 'data' => $submit_buttons, 'colspan' => 3 ) ), 'class' => 'profile_button' );
  }
  else {
    return FALSE;
  }

  // Wrap it together inside a form
  $content = '<form method="post">' . theme( 'table', $header, $rows, array( 'id' => 'patroninfo', 'cellspacing' => '0' ) ) . '</form>';
  return $content;
}

/**
 * Use form API to creat holds table
 *
 * @return string
 */
function sopac_user_holds_form( $form_state, $account = NULL, $max_disp = NULL ) {
  if ( !$account ) {
    global $user;
    $account = user_load( $user->uid );
  }

  $cardnum = $account->profile_pref_cardnum;
  $ils_pass = $user->locum_pass;
  $locum = sopac_get_locum();
  $holds = $locum->get_patron_holds( $cardnum, $ils_pass );

  if ( !count( $holds ) ) {
    $form['empty'] = array(
      '#type' => 'markup',
      '#value' => t( 'No items on hold.' ),
    );
    return $form;
  }

  $suspend_holds = variable_get( 'sopac_suspend_holds', FALSE );
  if ( $suspend_holds ) {
    return _sopac_user_holds_form_multirow( $holds );
  }

  $form = array(
    '#theme' => 'form_theme_bridge',
    '#bridge_to_theme' => 'sopac_user_holds_list',
    '#cardnum' => $cardnum,
    '#ils_pass' => $ils_pass,
  );

  $sopac_prefix = variable_get( 'sopac_url_prefix', 'cat/seek' ) . '/record/';
  $freezes_enabled = variable_get( 'sopac_hold_freezes_enable', 1 );

  $form['holds'] = array(
    '#tree' => TRUE,
    '#iterable' => TRUE,
  );

  foreach ( $holds as $hold ) {
    $bnum = $hold['bnum'];
    $hold_to_theme = array();
    $varname = $hold['varname'] ? $hold['varname'] : $bnum;

    $hold_to_theme['bnum'] = array(
      '#type' => 'value',
      '#value' => $bnum,
    );
    $hold_to_theme['title'] = array(
      '#type' => 'value',
      '#value' => $hold['title'],
    );
    $hold_to_theme['cancel'] = array(
      '#type' => 'checkbox',
      '#default_value' => FALSE,
    );
    $hold_to_theme['title_link'] = array(
      '#type' => 'markup',
      '#value' => l( t( $hold['title'] ), $sopac_prefix . $bnum ),
    );
    $hold_to_theme['status'] = array(
      '#type' => 'markup',
      '#value' => $hold['status']
    );
    $hold_to_theme['pickup'] = array(
      '#type' => 'markup',
      '#value' => $hold['pickuploc']['options'][$hold['pickuploc']['selected']],
    );
    if ( $freezes_enabled ) {
      if ( $hold['can_freeze'] ) {
        $hold_to_theme['freeze'] = array(
          '#type' => 'checkbox',
          '#default_value' => $hold['is_frozen'],
        );
      }
      else {
        $hold_to_theme['freeze'] = array(
          '#type' => 'markup',
          '#value' => '&nbsp;'
        );
      }
    }

    $form['holds'][$varname] = $hold_to_theme;
  }

  $form['submit'] = array(
    '#type' => 'submit',
    '#name' => 'op',
    '#value' => $freezes_enabled ? t( 'Update Holds' ) : t( 'Cancel Selected Holds' ),
  );
  return $form;
}

/**
 * Validate request to change holds.
 *
 * @param array   $form
 * @param array   $form_state
 */
function sopac_user_holds_form_validate( &$form, &$form_state ) {
  if ( !$account ) {
    global $user;
    $account = user_load( $user->uid );
  };

  // Set defaults to avoid errors when debugging.
  $pickup_changes = $suspend_from_changes = $suspend_to_changes = NULL;

  $update_holds = FALSE;
  $cancellations = array();
  $freeze_changes = array();
  $pickup_changes = array();
  $suspend_from_changes = array();
  $suspend_to_changes = array();

  // Get holds.
  $cardnum = $account->profile_pref_cardnum;
  $password = $user->locum_pass;
  $locum = sopac_get_locum();
  $holds = $locum->get_patron_holds( $cardnum, $password );
  // Should be how it comes back from locum
  $holds_by_bnum = array();
  foreach ( $holds as $hold ) {
    $holds_by_bnum[$hold['bnum']] = $hold;
  }
  $submitted_holds = $form_state['values']['holds'];

  $change_pickup = variable_get( 'sopac_changeable_pickup_location', FALSE );
  $suspend_holds = variable_get( 'sopac_suspend_holds', FALSE );
  if ( $suspend_holds ) {
    // Set up time object for use in validating suspension dates
    $sClosedByTimezone = $locum->locum_config['harvest_config']['timezone'];
    $date_object = new DateTime( now, new DateTimeZone( $sClosedByTimezone ) );
  }

  foreach ( $submitted_holds as $bnum => $hold_data ) {
    if ( $hold_data['cancel'] ) {
      $cancellations[$bnum] = TRUE;
      $update_holds = TRUE;
      continue;
    }
    $freeze_requested = $hold_data['freeze'];
    if ( $freeze_requested != $holds_by_bnum[$bnum]['is_frozen'] ) {
      $freeze_changes[$bnum] = $freeze_requested;
      $update_holds = TRUE;
    }
    if ( $change_pickup ) {
      $pickup_location = $hold_data['pickup'];
      if ( $pickup_location != $holds_by_bnum[$bnum]['pickuploc']['selected'] ) {
        $pickup_changes[$bnum] = $pickup_location;
        $update_holds = TRUE;
      }
    }
    if ( $suspend_holds ) {
      $suspend_from = $hold_data['suspend_from'];
      // Catch unchanged default.
      if ( $suspend_from == 'mm/dd/yyyy' ) {
        $suspend_from = '';
      }
      // Make sure it's a date (allow 2-digit years, but ask for 4).
      elseif ( !preg_match( '/^([1-9]|1[012])\/([1-9]|[12][0-9]|3[01])\/(20[1-9][0-9]|[1-9][0-9])$/', $suspend_from ) ) {
        form_set_error( "holds[$bnum][suspend_from", t( 'Please enter suspend dates in the form 4/15/1980 (mm/dd/yyyy).' ) );
      }
      elseif ( $suspend_from != $holds_by_bnum[$bnum]['start_suspend'] ) {
        $suspend_from_changes[$bnum] = $suspend_from;
        $update_holds = TRUE;
      }

      $suspend_to = $hold_data['suspend_to'];
      // Catch unchanged default.
      if ( $suspend_to == 'mm/dd/yyyy' ) {
        $suspend_to = '';
      }
      // Make sure it's a date (allow 2-digit years, but ask for 4).
      elseif ( !preg_match( '/^([1-9]|1[012])\/([1-9]|[12][0-9]|3[01])\/(20[1-9][0-9]|[1-9][0-9])$/', $suspend_to ) ) {
        form_set_error( "holds[$bnum][suspend_to", t( 'Please enter suspend dates in the form 4/15/1980 (mm/dd/yyyy).' ) );
      }
      elseif ( $suspend_to != $holds_by_bnum[$bnum]['end_suspend'] ) {
        $suspend_to_changes[$bnum] = $suspend_to;
        $update_holds = TRUE;
      }
      if ( $suspend_to && !$suspend_from ) {
        form_set_error( "holds][$bnum][suspend_to", t( 'You cannot set a suspend to date without a corresponding suspend from date.' ) );
      }
      elseif ( $suspend_to && $suspend_from ) {
        $date_parts = explode( '/', $suspend_from );
        $date_object->setDate( $date_parts[2], $date_parts[0], $date_parts[1] );
        $from_date = $date_object->format( 'Ymd' );
        $date_parts = explode( '/', $suspend_to );
        $date_object->setDate( $date_parts[2], $date_parts[0], $date_parts[1] );
        $to_date = $date_object->format( 'Ymd' );
        if ( $to_date < $from_date ) {
          form_set_error( "holds[$bnum][suspend_to", t( 'A suspend to date cannot be before the corresponding suspend from date.' ) );
        }
      }
    }
  }

  $errors = form_get_errors();
  if ( is_array( $errors ) ) {
    // Skip rest of this structure.
  }
  elseif ( !$update_holds ) {
    form_set_error( '', 'Your request to ' . $form['submit']['#value'] . ' did not include any changes.' );
  }
  // Store data for use by submit function.
  else {
    $form_state['sopac_user_holds'] = array(
      'cancellations' => $cancellations,
      'freeze_changes' => $freeze_changes,
      'pickup_changes' => $pickup_changes,
      'suspend_from_changes' => $suspend_from_changes,
      'suspend_to_changes' => $suspend_to_changes,
    );
  }
}

/**
 * Pass locum validated request to update holds.
 *
 * @param array   $form
 * @param array   $form_state
 */
function sopac_user_holds_form_submit( &$form, &$form_state ) {
  if ( !$account ) {
    global $user;
    $account = user_load( $user->uid );
  };

  $cardnum = $account->profile_pref_cardnum;
  $password = $user->locum_pass;
  $cancellations = $form_state['sopac_user_holds']['cancellations'];
  $freeze_changes = $form_state['sopac_user_holds']['freeze_changes'];
  $pickup_changes = $form_state['sopac_user_holds']['pickup_changes'];
  $suspend_changes = array(
    'from' => $form_state['sopac_user_holds']['suspend_from_changes'],
    'to' => $form_state['sopac_user_holds']['suspend_to_changes'],
  );
  $locum = sopac_get_locum();
  $locum->update_holds( $cardnum, $password, $cancellations, $freeze_changes, $pickup_changes, $suspend_changes );
}

/**
 * Fork to allow support for changing hold pickup location, and suspend dates. Uses
 * different tpl since extra options require different layout.
 *
 * @param array   $holds
 * @return array
 */
function _sopac_user_holds_form_multirow( $holds ) {
  // <CraftySpace+> TODO: do we need to check for multi-branch, else no pickup location?
  $form = array(
    '#theme' => 'form_theme_bridge',
    '#layout_theme' => 'sopac_user_holds_list_multirow',
  );

  $sopac_prefix = variable_get( 'sopac_url_prefix', 'cat/seek' ) . '/record/';
  $form['holds'] = array(
    '#tree' => TRUE,
    '#iterable' => TRUE,
  );
  foreach ( $holds as $hold ) {
    $bnum = $hold['bnum'];

    $form['holds'][$bnum] = array(
      'cancel' => array(
        '#type' => 'checkbox',
        '#default_value' => FALSE,
      ),
      'title_link' => array(
        '#type' => 'markup',
        '#value' => l( t( $hold['title'] ), $sopac_prefix . $bnum ),
      ),
      'status' => array(
        '#type' => 'markup',
        '#value' => $hold['status']
      ),
      'pickup' => array(
        '#type' => 'select',
        '#options' => sopac_get_branch_options(),
        '#default_value' => $hold['pickuploc']['selected'],
      ),
      'freeze' => array(
        '#type' => 'radios',
        '#default_value' => $hold['is_frozen'],
        '#options' => array( 0 => t( 'Active' ), 1 => t( 'Inactive' ) ),
      ),
      'suspend_from' => array(
        '#type' => 'textfield',
        '#title' => 'From',
        '#default_value' => $hold['start_suspend'] ? $hold['start_suspend'] : 'mm/dd/yyyy',
        '#attributes' => array( 'maxlength' => '10', 'size' => '15' ),
      ),
      'suspend_to' => array(
        '#type' => 'textfield',
        '#title' => 'To',
        '#default_value' => $hold['end_suspend'] ? $hold['end_suspend'] : 'mm/dd/yyyy',
        '#attributes' => array( 'maxlength' => '10', 'size' => '15' ),
      ),
    );
  }

  $form['submit'] = array(
    '#type' => 'submit',
    '#name' => 'op',
    '#value' => t( 'Update Holds' ),
  );

  return $form;
}

/**
 * A dedicated check-outs page to list all checkouts.
 */
function sopac_checkouts_page() {
  global $user;

  $account = user_load( $user->uid );
  $cardnum = $account->profile_pref_cardnum;
  $locum = sopac_get_locum();
  $userinfo = $locum->get_patron_info( $cardnum );
  $bcode_verify = sopac_bcode_isverified( $account );

  // Initialize the pager if need be
  if ( $pager_page_array[0] ) {
    $page = $pager_page_array[0] + 1;
  }
  else {
    $page = 1;
  }
  $page_offset = $limit * ( $page - 1 );

  if ( $bcode_verify ) {
    $account->bcode_verify = TRUE;
  }
  else {
    $account->bcode_verify = FALSE;
  }
  if ( $userinfo['pnum'] ) {
    $account->valid_card = TRUE;
  }
  else {
    $account->valid_card = FALSE;
  }
  
  profile_load_profile( &$user );

  if ( $account->valid_card && $bcode_verify ) {
    $content = sopac_user_chkout_table( &$user, &$locum );
  }
  elseif ( $account->valid_card && !$bcode_verify ) {
    $content = '<div class="error">' . variable_get( 'sopac_uv_cardnum', t( 'The card number you have provided has not yet been verified by you.  In order to make sure that you are the rightful owner of this library card number, we need to ask you some simple questions.' ) ) . '</div>' . drupal_get_form( 'sopac_bcode_verify_form', $account->uid, $cardnum );
  }
  elseif ( $cardnum && !$account->valid_card ) {
    $content = '<div class="error">' . variable_get( 'sopac_invalid_cardnum', t( 'It appears that the library card number stored on our website is invalid. If you have received a new card, or feel that this is an error, please click on the card number above to change it to your most recent library card. If you need further help, please contact us.' ) ) . '</div>';
  }
  elseif ( !$user->uid ) {
    $content = '<div class="error">' . t( 'You must be ' ) . l( t( 'logged in' ), 'user' ) . t( ' to view this page.' ) . '</div>';
  }
  elseif ( !$cardnum ) {
    $content = '<div class="error">' . t( 'You must register a valid ' ) . l( t( 'library card number' ), 'user/' . $user->uid . '/edit/Preferences' ) . t( ' to view this page.' ) . '</div>';
  }

  return $content;
}

/**
 * A dedicated checkout history page.
 */
function sopac_checkout_history_page() {
  global $user;

  $account = user_load( $user->uid );
  $cardnum = $account->profile_pref_cardnum;
  $locum = sopac_get_locum();
  $insurge = sopac_get_insurge();
  $userinfo = $locum->get_patron_info( $cardnum );
  $bcode_verify = sopac_bcode_isverified( $account );
  $getvars = sopac_parse_get_vars();
  $pager_page_array = explode( ',', $getvars['page'] );
  $page_limit = 25; // TODO. Hard coded for now
  $page = isset( $_GET['page'] ) ? $_GET['page'] : 0;
  $offset = ( $page_limit * $page );
  $search_str = isset( $_GET['search'] ) ? $_GET['search'] : 0;
  $sortables = array('title_up', 'title_down', 'author_up', 'author_down');
  if (in_array($_GET['sort'], $sortables)) { $sort = $_GET['sort']; } else { $sort = NULL; }

  if ( $bcode_verify ) {
      $account->bcode_verify = TRUE;
    }
    else {
      $account->bcode_verify = FALSE;
    }
    if ( $userinfo['pnum'] ) {
      $account->valid_card = TRUE;
    }
    else {
      $account->valid_card = FALSE;
    }
  profile_load_profile( &$user );

  $db_obj = db_fetch_object( db_query( "SELECT pnum FROM {sopac_card_verify} WHERE uid = %d AND cardnum = '%s' AND verified > 0", $user->uid, $cardnum ) );
  $pnum = $db_obj->pnum;


  // Get the time since the last update
  $last_import_bib = db_result( db_query( "SELECT last_check_id FROM {sopac_last_hist_check} WHERE uid = '" . $user->uid . "'" ) );
  $last_import_bib = $last_import_bib ? $last_import_bib : 0;
  // Check profile to see if CO hist is enabled
  $user_co_hist_enabled = $account->profile_pref_cohist;

  if (!$_POST['op']) {
    if ( $user_co_hist_enabled ) {
      // CO hist is enabled, would you like to disable it?
      $content .= t('Your checkout history is currently being saved. ') . l( 'Click here', 'user/' . $user->uid . '/edit/Preferences' ) . t(' to turn off this feature.') . '<br /><br />';
    } else {
      // CO hist is not enabled, would you like to enable it?
      $content .= t('Your checkout history is not currently being saved. ') . l( 'Click here', 'user/' . $user->uid . '/edit/Preferences' ) . t(' to turn on this feature.') . '<br /><br />';
    }
  }

  // Pull newest history into Insurge
  $latest_history_arr = $locum->get_patron_checkout_history( $cardnum, $locum_pass, $last_import_bib );

  foreach ($latest_history_arr AS $hist_item) {
    $insurge->add_checkout_history($hist_item['hist_id'], $user->uid, $hist_item['bibid'], $hist_item['checkoutdate'], $hist_item['title'], $hist_item['author']);
    if ($hist_item['hist_id'] > $last_import_bib) {
      $last_import_bib = $hist_item['hist_id'];
    }
  }
  db_query( "DELETE FROM {sopac_last_hist_check} WHERE uid = %d", $user->uid );
  db_query( "INSERT INTO {sopac_last_hist_check} VALUES (%d, NOW(), %d)", $user->uid, $last_import_bib );

  // Simple search form
  // TODO: Search form


  // Grab checkout history from Insurge
  // TODO: Parse get vars for sort_by and search_txt, passit it to get_checkout_history
  $hist_arr = $insurge->get_checkout_history($user->uid, $search_str, $sort, NULL, $offset);
  $hist_count = count($hist_arr);
  sopac_pager_init( $hist_count, 0, $page_limit );
  $hist_arr = $insurge->get_checkout_history($user->uid, $search_str, $sort, $page_limit, $offset);

  if (($hist_count > $page_limit) && !$_POST['op']) { $show_pager = TRUE; }
  if ($show_pager) { $content .= theme( 'pager', NULL, $page_limit, 0, NULL, 6 ) . '<br />'; }



  if ( $account->valid_card && $bcode_verify ) {
    if ($hist_count) {
      $content .= drupal_get_form( 'sopac_user_history_form', $account, $hist_arr, $sort, $search_str );
    } else {
      $content .= t('You do not currently have anything in your checkout history.');
    }
  }
  elseif ( $account->valid_card && !$bcode_verify ) {
    $content .= '<div class="error">' . variable_get( 'sopac_uv_cardnum', t( 'The card number you have provided has not yet been verified by you.  In order to make sure that you are the rightful owner of this library card number, we need to ask you some simple questions.' ) ) . '</div>' . drupal_get_form( 'sopac_bcode_verify_form', $account->uid, $cardnum );
  }
  elseif ( $cardnum && !$account->valid_card ) {
    $content .= '<div class="error">' . variable_get( 'sopac_invalid_cardnum', t( 'It appears that the library card number stored on our website is invalid. If you have received a new card, or feel that this is an error, please click on the card number above to change it to your most recent library card. If you need further help, please contact us.' ) ) . '</div>';
  }
  elseif ( !$user->uid ) {
    $content .= '<div class="error">' . t( 'You must be ' ) . l( t( 'logged in' ), 'user' ) . t( ' to view this page.' ) . '</div>';
  }
  elseif ( !$cardnum ) {
    $content .= '<div class="error">' . t( 'You must register a valid ' ) . l( t( 'library card number' ), 'user/' . $user->uid . '/edit/Preferences' ) . t( ' to view this page.' ) . '</div>';
  }


  if ($show_pager) { $content .= theme( 'pager', NULL, $page_limit, 0, NULL, 6 ); }


  return $content;

}

/**
 * Use form API to creat checkout history table
 *
 * @return string
 */

function sopac_user_history_form( $form_state, $account = NULL, $hist_arr = NULL, $sort_by = NULL, $search_str = NULL ) {
  if ( !$account ) {
    global $user;
    $account = user_load( $user->uid );
  }

  if (isset($form_state['values']['history'])) {
    return sopac_user_history_form_confirm($form_state);
  }

  $form['history'] = array(
    '#tree' => TRUE,
    '#iterable' => TRUE,
  );

  $form['#validate'][] = 'sopac_user_history_form_validate';
  $form['#submit'][] = 'sopac_user_history_form_submit';

  $form['sort_by'] = array(
    '#type' => 'markup',
    '#value' => $sort_by,
  );

  // Construct the form
  if ( !count( $hist_arr ) ) {
    $form['empty'] = array(
      '#type' => 'markup',
      '#value' => t( 'No items in your history, currently.' ),
    );
    return $form;
  }

  $sopac_prefix = variable_get( 'sopac_url_prefix', 'cat/seek' ) . '/record/';

  foreach ( $hist_arr as $hist_item ) {
    $hist_to_theme = array();
    $bnum = $hist_item['bnum'];
    $varname = $hist_item['hist_id'] ? $hist_item['hist_id'] : $bnum;

    $hist_to_theme['bnum'] = array(
      '#type' => 'value',
      '#value' => $bnum,
    );
    $hist_to_theme['delete'] = array(
      '#type' => 'checkbox',
      '#default_value' => FALSE,
    );
    $hist_to_theme['title_link'] = array(
      '#type' => 'markup',
      '#value' => l( t( ucwords($hist_item['title']) ), $sopac_prefix . $bnum ),
    );
    $hist_to_theme['author'] = array(
      '#type' => 'markup',
      '#value' => $hist_item['author']
    );
    $hist_to_theme['codate'] = array(
      '#type' => 'markup',
      '#value' => $hist_item['codate']
    );

    $form['history'][$varname] = $hist_to_theme;
  }

  $form['submit'] = array(
    '#type' => 'submit',
    '#name' => 'op',
    '#value' => t( 'Delete Selected Items' ),
  );

  $form['deleteall'] = array(
    '#type' => 'submit',
    '#name' => 'op',
    '#value' => t( 'Delete All Items' ),
  );

  return $form;

}

function sopac_user_history_form_confirm(&$form_state) {

  $desc = '<div>Are you sure? This will permanently delete history items.</div><br />';
  // Tell the submit handler to process the form
  $form['process'] = array('#type' => 'hidden', '#value' => 'true');
  // Make sure the form redirects in the end
  $form['destination'] = array('#type' => 'hidden', '#value' => 'user/library/history');
  return confirm_form($form,
                      'Are you sure?',
                      'user/library/history',
                      $desc,
                      'Delete',
                      'Cancel');
}

function sopac_user_history_form_validate( &$form, &$form_state ) {
  return TRUE;
}

function sopac_user_history_form_submit( &$form, &$form_state ) {
  global $user;

  if ($form_state['values']['confirm']) { 
    profile_load_profile( &$user );
    if ( !$user->profile_pref_cardnum ) {
      return;
    }
    // Process form elements
    $insurge = sopac_get_insurge();
    $locum = sopac_get_locum();
    $locum_pass = substr( $user->pass, 0, 7 );
    if ($form['#parameters'][1]['values']['op'] == t('Delete All Items')) {
      // Delete all history
      $insurge->delete_checkout_history($user->uid, NULL, 1);
      $locum->delete_patron_checkout_history($user->profile_pref_cardnum, $locum_pass, NULL, TRUE );
    } else {
      // Selective delete
      $form_values = $form['#parameters'][1]['values']['history'];

      foreach ($form_values as $hist_id => $hist_info) {
        if ($hist_info['delete'] == 1) {
          // Delete $hist_id
          $insurge->delete_checkout_history($user->uid, $hist_id);
          $locum->delete_patron_checkout_history($user->profile_pref_cardnum, $locum_pass, $hist_id, FALSE );
        }
      }
    }
  } else {
    $form_state['rebuild'] = TRUE;
  }
}

/**
 * Handle toggling checkout history on or off.
 */
function sopac_checkout_history_toggle( $action ) {
  global $user;
  if ( $action != 'in' && $action != 'out' ) { drupal_goto( 'user/checkouts/history' ); }
  $adjective = $action == 'in' ? t( 'on' ) : t( 'off' );
  profile_load_profile( &$user );
  if ( $user->profile_pref_cardnum ) {
    if ( !$_GET['confirm'] ) {
      $confirm_link = l( t( 'confirm' ), $_GET['q'], array( 'query' => 'confirm=true' ) );
      $content = "<div>Please $confirm_link that you wish to turn $adjective your checkout history.";
      if ( $action == 'out' ) {
        $content .= ' ' . t( 'Please note: this will delete your entire checkout history.' );
      }
    }
    else {
      $locum = sopac_get_locum();
      $locum_pass = substr( $user->pass, 0, 7 );
      $cardnum = $user->profile_pref_cardnum;
      $success = $locum->set_patron_checkout_history( $cardnum, $locum_pass, $action );
      if ( $success === TRUE ) {
        $content = "<div>Your checkout history has been turned $adjective.</div>";
      }
      else {
        $content = "<div>An error occurred. Your checkout history has not been turned $adjective. Please try again.</div>";
      }
    }
  }
  else {
    $content = '<div>' . t( 'Please register your library card to take advantage of this feature.' ) . '</div>';
  }
  return $content;
}

/**
 * A dedicated holds page to list all holds.
 */
function sopac_holds_page() {
  global $user;

  $account = user_load( $user->uid );
  $cardnum = $account->profile_pref_cardnum;
  $locum = sopac_get_locum();
  $userinfo = $locum->get_patron_info( $cardnum );
  $bcode_verify = sopac_bcode_isverified( $account );
  if ( $bcode_verify ) {
    $account->bcode_verify = TRUE;
  }
  else {
    $account->bcode_verify = FALSE;
  }
  if ( $userinfo['pnum'] ) {
    $account->valid_card = TRUE;
  }
  else {
    $account->valid_card = FALSE;
  }
  profile_load_profile( &$user );

  if ( $account->valid_card && $bcode_verify ) {
    $content = drupal_get_form( 'sopac_user_holds_form' );
  }
  elseif ( $account->valid_card && !$bcode_verify ) {
    $content = '<div class="error">' . variable_get( 'sopac_uv_cardnum', t( 'The card number you have provided has not yet been verified by you.  In order to make sure that you are the rightful owner of this library card number, we need to ask you some simple questions.' ) ) . '</div>' . drupal_get_form( 'sopac_bcode_verify_form', $account->uid, $cardnum );
  }
  elseif ( $cardnum && !$account->valid_card ) {
    $content = '<div class="error">' . variable_get( 'sopac_invalid_cardnum', t( 'It appears that the library card number stored on our website is invalid. If you have received a new card, or feel that this is an error, please click on the card number above to change it to your most recent library card. If you need further help, please contact us.' ) ) . '</div>';
  }
  elseif ( !$user->uid ) {
    $content = '<div class="error">' . t( 'You must be ' ) . l( t( 'logged in' ), 'user' ) . t( ' to view this page.' ) . '</div>';
  }
  elseif ( !$cardnum ) {
    $content = '<div class="error">' . t( 'You must register a valid ' ) . l( t( 'library card number' ), 'user/' . $user->uid . '/edit/Preferences' ) . t( ' to view this page.' ) . '</div>';
  }

  return $content;
}

/**
 * A dedicated page for managing fines and payments.
 */
function sopac_fines_page() {
  global $user;

  $locum = sopac_get_locum();
  profile_load_profile( &$user );

  if ( $user->profile_pref_cardnum && sopac_bcode_isverified( &$user ) ) {
    $locum_pass = substr( $user->pass, 0, 7 );
    $cardnum = $user->profile_pref_cardnum;
    $fines = $locum->get_patron_fines( $cardnum, $locum_pass );

    if ( !count( $fines ) ) {
      $notice = t( 'You do not have any fines, currently.' );
    }
    else {
      $header = array( '', t( 'Amount' ), t( 'Description' ) );
      $fine_total = (float) 0;
      foreach ( $fines as $fine ) {
        $col1 = variable_get( 'sopac_payments_enable', 1 ) ? '<input type="checkbox" name="varname[]" value="' . $fine['varname'] . '">' : '';
        $col1 = '';
        $rows[] = array(
          $col1,
          '$' . number_format( $fine['amount'], 2 ),
          $fine['desc'],
        );
        $hidden_vars .= '<input type="hidden" name="fine_summary[' . $fine['varname'] . '][amount]" value="' . addslashes( $fine['amount'] ) . '">';
        $hidden_vars .= '<input type="hidden" name="fine_summary[' . $fine['varname'] . '][desc]" value="' . addslashes( $fine['desc'] ) . '">';
        $fine_total = $fine_total + $fine['amount'];
      }
      $rows[] = array( '<strong>Total:</strong>', '$' . number_format( $fine_total, 2 ), '' );
      $submit_button = '<input type="submit" value="' . t( 'Pay Selected Charges' ) . '">';
      $submit_button = 'Payment temporarily disabled.';
      if ( variable_get( 'sopac_payments_enable', 1 ) ) {
        $rows[] = array( 'data' => array( array( 'data' => $submit_button, 'colspan' => 3 ) ), 'class' => 'profile_button' );
      }
      $fine_table = '<form method="post" action="/user/fines/pay">' . theme( 'table', $header, $rows, array( 'id' => 'patroninfo', 'cellspacing' => '0' ) ) . $hidden_vars . '</form>';
      $notice = t( 'Your current fine balance is $' ) . number_format( $fine_total, 2 ) . '.';
    }
  }
  else {
    $notice = t( 'You do not yet have a library card validated with our system.  You can add and validate a card using your ' ) . l( t( 'account page' ), 'user' ) . '.';
  }

  $result_page = theme( 'sopac_fines', $notice, $fine_table, &$user );
  return '<p>'. t( $result_page ) .'</p>';
}

/**
 * A dedicated page for viewing payment information.
 */
function sopac_finespaid_page() {
  global $user;
  $limit = 20; // TODO Make this configurable

  if ( count( $_POST['payment_id'] ) ) {
    foreach ( $_POST['payment_id'] as $pid ) {
      db_query( 'DELETE FROM {sopac_fines_paid} WHERE payment_id = ' . $pid . ' AND uid = ' . $user->uid );
    }
  }

  if ( db_result( db_query( 'SELECT COUNT(*) FROM {sopac_fines_paid} WHERE uid = ' . $user->uid ) ) ) {
    $header = array( '', 'Payment Date', 'Payment Description', 'Amount' );
    $dbq = pager_query( 'SELECT payment_id, UNIX_TIMESTAMP(trans_date) as trans_date, fine_desc, amount FROM {sopac_fines_paid} WHERE uid = ' . $user->uid . ' ORDER BY trans_date DESC', $limit );
    while ( $payment_arr = db_fetch_array( $dbq ) ) {
      $checkbox = '<input type="checkbox" name="payment_id[]" value="' . $payment_arr['payment_id'] . '">';
      $payment_date = date( 'm-d-Y, H:i:s', $payment_arr['trans_date'] );
      $payment_desc = $payment_arr['fine_desc'];
      $payment_amt = '$' . number_format( $payment_arr['amount'], 2 );
      $rows[] = array( $checkbox, $payment_date, $payment_desc, $payment_amt );
    }
    $submit_button = '<input type="submit" value="' . t( 'Remove Selected Payment Records' ) . '">';
    $rows[] = array( 'data' => array( array( 'data' => $submit_button, 'colspan' => 4 ) ), 'class' => 'profile_button' );
    $page_disp = '<form method="post">' . theme( 'pager', NULL, $limit, 0, NULL, 6 ) . theme( 'table', $header, $rows, array( 'id' => 'patroninfo', 'cellspacing' => '0' ) ) . '</form>';
  }
  else {
    $page_disp = t( 'You do not have any payments on record.' );
  }

  return $page_disp;
}

function sopac_makepayment_page() {
  global $user;

  $locum = sopac_get_locum();
  profile_load_profile( &$user );

  if ( $user->profile_pref_cardnum && sopac_bcode_isverified( &$user ) ) {
    if ( $_POST['varname'] && is_array( $_POST['varname'] ) ) {
      $varname = $_POST['varname'];
    }
    else {
      $varname = explode( '|', $_POST['varname'] );
    }
    $locum_pass = substr( $user->pass, 0, 7 );
    $cardnum = $user->profile_pref_cardnum;
    $fines = $locum->get_patron_fines( $cardnum, $locum_pass );
    if ( !count( $fines ) || !count( $varname ) ) {
      $notice = t( 'You did not select any payable fines.' );
    }
    else {
      $header = array( '', t( 'Amount' ), t( 'Description' ) );
      $fine_total = (float) 0;
      foreach ( $fines as $fine ) {
        if ( in_array( $fine['varname'], $varname ) ) {
          $rows[] = array(
            '',
            '$' . number_format( $fine['amount'], 2 ),
            $fine['desc'],
          );
          $fine_total = $fine_total + $fine['amount'];
          $hidden_vars_arr[$fine['varname']]['amount'] = $_POST['fine_summary'][$fine['varname']]['amount'];
          $hidden_vars_arr[$fine['varname']]['desc'] = $_POST['fine_summary'][$fine['varname']]['desc'];
        }
      }
      $payment_form = drupal_get_form( 'sopac_fine_payment_form', $varname, (string) $fine_total, $hidden_vars_arr );
      $rows[] = array( '<strong>Total:</strong>', '$' . number_format( $fine_total, 2 ), '' ) ;
      $fine_table = theme( 'table', $header, $rows, array( 'id' => 'patroninfo', 'cellspacing' => '0' ) );
      $notice = t( 'You have selected to pay the following fines:' );
    }
  }
  else {
    $notice = t( 'You do not yet have a library card validated with our system.  You can add and validate a card using your ' ) . l( t( 'account page' ), 'user' ) . '.';
  }

  $result_page = theme( 'sopac_fines', $notice, $fine_table, &$user, $payment_form );
  return '<p>'. t( $result_page ) .'</p>';
}

function sopac_fine_payment_form() {
  global $user;

  $args = func_get_args();
  $varname = $args[1];
  $fine_total = $args[2];
  $hidden_vars_arr = $args[3];

  $form['#redirect'] = 'user/fines';
  $form['sopac_payment_billing_info'] = array(
    '#type' => 'fieldset',
    '#title' => t( 'Billing Information' ),
    '#collapsible' => FALSE,
  );

  $form['sopac_payment_billing_info']['name'] = array(
    '#type' => 'textfield',
    '#title' => t( 'Name on the credit card' ),
    '#size' => 48,
    '#maxlength' => 200,
    '#required' => TRUE,
  );

  $form['sopac_payment_billing_info']['address1'] = array(
    '#type' => 'textfield',
    '#title' => t( 'Billing Address' ),
    '#size' => 48,
    '#maxlength' => 200,
    '#required' => TRUE,
  );

  $form['sopac_payment_billing_info']['city'] = array(
    '#type' => 'textfield',
    '#title' => t( 'City/Town' ),
    '#size' => 32,
    '#maxlength' => 200,
    '#required' => TRUE,
  );

  $form['sopac_payment_billing_info']['state'] = array(
    '#type' => 'textfield',
    '#title' => t( 'State' ),
    '#size' => 3,
    '#maxlength' => 2,
    '#required' => TRUE,
  );

  $form['sopac_payment_billing_info']['zip'] = array(
    '#type' => 'textfield',
    '#title' => t( 'ZIP Code' ),
    '#size' => 7,
    '#maxlength' => 200,
    '#required' => TRUE,
  );

  $form['sopac_payment_billing_info']['email'] = array(
    '#type' => 'textfield',
    '#title' => t( 'Email Address' ),
    '#size' => 48,
    '#maxlength' => 200,
    '#required' => TRUE,
  );

  $form['sopac_payment_cc_info'] = array(
    '#type' => 'fieldset',
    '#title' => t( 'Credit Card Information' ),
    '#collapsible' => FALSE,
  );

  $form['sopac_payment_cc_info']['ccnum'] = array(
    '#type' => 'textfield',
    '#title' => t( 'Credit Card Number' ),
    '#size' => 24,
    '#maxlength' => 20,
    '#required' => TRUE,
  );

  $form['sopac_payment_cc_info']['ccexpmonth'] = array(
    '#type' => 'textfield',
    '#title' => t( 'Expiration Month' ),
    '#size' => 4,
    '#maxlength' => 2,
    '#required' => TRUE,
    '#prefix' => '<table class="fines-form-table"><tr><td>',
    '#suffix' => '</td>',
  );

  $form['sopac_payment_cc_info']['ccexpyear'] = array(
    '#type' => 'textfield',
    '#title' => t( 'Expiration Year' ),
    '#size' => 5,
    '#maxlength' => 4,
    '#required' => TRUE,
    '#prefix' => '<td>',
    '#suffix' => '</td></tr></table>',
  );

  $form['sopac_payment_cc_info']['ccseccode'] = array(
    '#type' => 'textfield',
    '#title' => t( 'Security Code' ),
    '#size' => 5,
    '#maxlength' => 5,
    '#required' => TRUE,
  );

  foreach ( $hidden_vars_arr as $hkey => $hvar ) {
    $form['sopac_payment_form']['fine_summary[' . $hkey . '][amount]'] = array( '#type' => 'hidden', '#value' => $hvar['amount'] );
    $form['sopac_payment_form']['fine_summary[' . $hkey . '][desc]'] = array( '#type' => 'hidden', '#value' => $hvar['desc'] );
  }
  $form['sopac_payment_form']['varname'] = array( '#type' => 'hidden', '#value' => implode( '|', $varname ) );
  $form['sopac_payment_form']['total'] = array( '#type' => 'hidden', '#value' => $fine_total );
  $form['sopac_savesearch_form']['submit'] = array( '#type' => 'submit', '#value' => t( 'Make Payment' ) );

  return $form;
}

function sopac_fine_payment_form_submit( $form, &$form_state ) {
  global $user;
  $locum = sopac_get_locum();
  profile_load_profile( &$user );
  $locum_pass = substr( $user->pass, 0, 7 );

  if ( $user->profile_pref_cardnum && sopac_bcode_isverified( &$user ) ) {
    $fines = $locum->get_patron_fines( $cardnum, $locum_pass );
    $payment_details['name'] = $form_state['values']['name'];
    $payment_details['address1'] = $form_state['values']['address1'];
    $payment_details['city'] = $form_state['values']['city'];
    $payment_details['state'] = $form_state['values']['state'];
    $payment_details['zip'] = $form_state['values']['zip'];
    $payment_details['email'] = $form_state['values']['email'];
    $payment_details['ccnum'] = $form_state['values']['ccnum'];
    $payment_details['ccexpmonth'] = $form_state['values']['ccexpmonth'];
    $payment_details['ccexpyear'] = $form_state['values']['ccexpyear'];
    $payment_details['ccseccode'] = $form_state['values']['ccseccode'];
    $payment_details['total'] = $form_state['values']['total'];
    $payment_details['varnames'] = explode( '|', $form_state['values']['varname'] );
    $payment_result = $locum->pay_patron_fines( $user->profile_pref_cardnum, $locum_pass, $payment_details );

    if ( !$payment_result['approved'] ) {
      if ( $payment_result['reason'] ) {
        $error = '<strong>' . t( 'Your payment was not processed:' ) . '</strong> ' . $payment_result['reason'];
      }
      else {
        $error = t( 'We were unable to process your payment.' );
      }
      drupal_set_message( t( '<span class="fine-notice">' . $error . '</span>' ) );
      if ( $payment_result['error'] ) {
        drupal_set_message( t( '<span class="fine-notice">' . $payment_result['error'] . '</span>' ) );
      }
    }
    else {
      foreach ( $_POST['fine_summary'] as $fine_var => $fine_var_arr ) {
        $fine_desc = db_escape_string( $fine_var_arr['desc'] );
        $sql = 'INSERT INTO {sopac_fines_paid} (payment_id, uid, amount, fine_desc) VALUES (0, ' . $user->uid . ', ' . $fine_var_arr['amount'] . ', "' . $fine_desc . '")';
        db_query( $sql );
      }
      $amount = '$' . number_format( $form_state['values']['total'], 2 );
      drupal_set_message( '<span class="fine-notice">' . t( 'Your payment of ' ) . $amount . t( ' was successful.  Thank-you!' ) . '</span>' );
    }
  }
}


/**
 * A dedicated page for showing and managing saved searches from the catalog.
 */
function sopac_saved_searches_page() {
  global $user;
  $limit = 20; // TODO Make this configurable

  if ( count( $_POST['search_id'] ) ) {
    foreach ( $_POST['search_id'] as $sid ) {
      db_query( 'DELETE FROM {sopac_saved_searches} WHERE search_id = ' . $sid . ' AND uid = ' . $user->uid );
    }
  }

  if ( db_result( db_query( 'SELECT COUNT(*) FROM {sopac_saved_searches} WHERE uid = ' . $user->uid ) ) ) {
    $header = array( '', 'Search Description', '' );
    $dbq = pager_query( 'SELECT * FROM {sopac_saved_searches} WHERE uid = ' . $user->uid . ' ORDER BY savedate DESC', $limit );
    while ( $search_arr = db_fetch_array( $dbq ) ) {
      $checkbox = '<input type="checkbox" name="search_id[]" value="' . $search_arr['search_id'] . '">';
      $parts = explode( '?', $search_arr['search_url'] );
      $search_desc = l( $search_arr['search_desc'], $parts[0], array( 'query' => $parts[1] ) );
      $search_feed_url = sopac_update_url( $search_arr['search_url'], 'output', 'rss' );
      $search_feed = theme_feed_icon( $search_feed_url, 'RSS Feed: ' . $search_arr['search_desc'] );
      $rows[] = array( $checkbox, $search_desc, $search_feed );
    }
    $submit_button = '<input type="submit" value="' . t( 'Remove Selected Searches' ) . '">';
    $rows[] = array( 'data' => array( array( 'data' => $submit_button, 'colspan' => 3 ) ), 'class' => 'profile_button' );
    $page_disp = '<form method="post">' . theme( 'pager', NULL, $limit, 0, NULL, 6 ) . theme( 'table', $header, $rows, array( 'id' => 'patroninfo', 'cellspacing' => '0' ) ) . '</form>';
  }
  else {
    $page_disp = '<div class="overview-nodata">' . t( 'You do not currently have any saved searches.' ) . '</div>';
  }

  return $page_disp;
}


/**
 * Returns the form array for saving searches
 *
 * @return array Drupal form array.
 */
function sopac_savesearch_form() {
  global $user;

  $search_path = str_replace( '/savesearch/', '/search/', $_GET['q'] );
  $search_query = sopac_make_pagevars( sopac_parse_get_vars() );
  $uri_arr = sopac_parse_uri();

  $form_desc = 'How would you like to label your ' . $uri_arr[1] . ' search for "' . l( $uri_arr[2], $search_path, array( 'query' => $search_query ) ) . '" ?';
  $form['#redirect'] = 'user/library/searches';
  $form['sopac_savesearch_form'] = array(
    '#type' => 'fieldset',
    '#title' => t( $form_desc ),
    '#collapsible' => FALSE,
  );

  $form['sopac_savesearch_form']['searchname'] = array(
    '#type' => 'textfield',
    '#title' => t( 'Search Label' ),
    '#size' => 48,
    '#maxlength' => 128,
    '#required' => TRUE,
    '#default_value' => 'My custom ' . $uri_arr[1] . ' search for "' . $uri_arr[2] . '"',
  );

  $form['sopac_savesearch_form']['uri'] = array( '#type' => 'hidden', '#value' => $search_path . '?' . $search_query );
  $form['sopac_savesearch_form']['submit'] = array( '#type' => 'submit', '#value' => t( 'Save' ) );

  return $form;

}


function sopac_savesearch_form_submit( $form, &$form_state ) {
  global $user;

  $desc = db_escape_string( $form_state['values']['searchname'] );
  db_query( 'INSERT INTO {sopac_saved_searches} VALUES (0, ' . $user->uid . ', NOW(), "' . $desc . '", "' . $form_state['values']['uri'] . '")' );

  $parts = explode( '?', $form_state['values']['uri'] );
  $submsg = '<strong>»</strong> ' . t( 'You have saved this search.' ) . '<br /><strong>»</strong> ' . l( t( 'Return to your search' ), $parts[0], array( 'query' => $parts[1] ) ) . '<br /><br />';
  drupal_set_message( $submsg );

}


function sopac_update_locum_acct( $op, &$edit, &$account ) {

  $locum = sopac_get_locum();

  // Make sure we're all legit on this account
  $cardnum = $account->profile_pref_cardnum;
  if ( !$cardnum ) {
    return 0;
  }
  $userinfo = $locum->get_patron_info( $cardnum );
  $bcode_verify = sopac_bcode_isverified( $account );
  if ( $bcode_verify ) {
    $account->bcode_verify = TRUE;
  }
  else {
    $account->bcode_verify = FALSE;
  }
  if ( $userinfo['pnum'] ) {
    $account->valid_card = TRUE;
  }
  else {
    $account->valid_card = FALSE;
  }
  if ( !$account->valid_card || !$bcode_verify ) {
    return 0;
  }

  if ( $edit['mail'] && $pnum ) {
    // TODO update email. etc.
  }
}

/**
 * Creates and returns the barcode/patron card number verification form.  It also does the neccesary processing
 * If this function has just successfully processed a form result, then it will instead return a message indicating thus.
 *
 * @param string  $cardnum Library patron barcode/card number
 * @return string Either the verification form or a confirmation of success.
 */
function sopac_bcode_verify_form() {

  $args = func_get_args();

  if ( variable_get( 'sopac_require_cfg', 'one' ) == 'one' ) {
    $req_flds = FALSE;
    $form_desc = t( 'Please correctly <strong>answer <u>one</u> of the following questions</strong>:' );
  }
  else {
    $req_flds = TRUE;
    $form_desc = t( 'Please correctly <strong>answer <u>all</u> of the following questions</strong>:' );
  }

  $form['sopac_card_verify'] = array(
    '#type' => 'fieldset',
    '#title' => t( 'Verify your Library Card Number' ),
    '#description' => t( $form_desc ),
    '#collapsible' => FALSE,
    '#validate' => 'sopac_bcode_verify_form_validate',
  );

  if ( variable_get( 'sopac_require_name', 1 ) ) {
    $form['sopac_card_verify']['last_name'] = array(
      '#type' => 'textfield',
      '#title' => t( 'What is your last name?' ),
      '#size' => 32,
      '#maxlength' => 128,
      '#required' => $req_flds,
      '#value' => $_POST['last_name'],
    );
  }

  if ( variable_get( 'sopac_require_streetname', 1 ) ) {
    $form['sopac_card_verify']['streetname'] = array(
      '#type' => 'textfield',
      '#title' => t( 'What is the name of the street you live on?' ),
      '#size' => 24,
      '#maxlength' => 32,
      '#required' => $req_flds,
      '#value' => $_POST['streetname'],
    );
  }

  if ( variable_get( 'sopac_require_tel', 1 ) ) {
    $form['sopac_card_verify']['telephone'] = array(
      '#type' => 'textfield',
      '#title' => t( 'What is your telephone number?' ),
      '#description' => t( "Please provide your area code as well as your phone number, eg: 203-555-1234." ),
      '#size' => 18,
      '#maxlength' => 24,
      '#required' => $req_flds,
      '#value' => $_POST['telephone'],
    );
  }

  $form['sopac_card_verify']['vfy_post'] = array( '#type' => 'hidden', '#value' => '1' );
  $form['sopac_card_verify']['uid'] = array( '#type' => 'hidden', '#value' => $args[1] );
  $form['sopac_card_verify']['cardnum'] = array( '#type' => 'hidden', '#value' => $args[2] );
  $form['sopac_card_verify']['vfy_submit'] = array( '#type' => 'submit', '#value' => t( 'Verify!' ) );

  return $form;
}

function sopac_bcode_verify_form_validate( $form, $form_state ) {
  global $account;

  $locum = sopac_get_locum();
  $cardnum = $form_state['values']['cardnum'];
  $uid = $form_state['values']['uid'];
  $userinfo = $locum->get_patron_info( $cardnum );
  $pnum = $userinfo['pnum'];
  $numreq = 0;
  $correct = 0;
  $validated = FALSE;

  $req_cfg = variable_get( 'sopac_require_cfg', 'one' );

  // Match the name given
  if ( variable_get( 'sopac_require_name', 1 ) ) {
    if ( trim( $form_state['values']['last_name'] ) ) {
      $locum_name = ereg_replace( "[^A-Za-z0-9 ]", "", trim( strtolower( $userinfo['name'] ) ) );
      $sub_name = ereg_replace( "[^A-Za-z0-9 ]", "", trim( strtolower( $form_state['values']['last_name'] ) ) );
      if ( preg_match( '/\b' . $sub_name . '\b/i', $locum_name ) ) {
        $correct++;
      }
      else {
        $error[] = t( 'The last name you entered does not appear to match what we have on file.' );
      }
    }
    else {
      $error[] = t( 'You did not provide a last name.' );
    }
    $numreq++;
  }

  if ( variable_get( 'sopac_require_streetname', 1 ) ) {
    if ( trim( $form_state['values']['streetname'] ) ) {
      $locum_addr = ereg_replace( "[^A-Za-z ]", "", trim( strtolower( $userinfo['address'] ) ) );
      $sub_addr = ereg_replace( "[^A-Za-z ]", "", trim( strtolower( $form_state['values']['streetname'] ) ) );
      $sub_addr_arr = explode( ' ', $sub_addr );
      if ( strlen( $sub_addr_arr[0] ) == 1 || $sub_addr_arr[0] == 'north' || $sub_addr_arr[0] == 'east' || $sub_addr_arr[0] == 'south' || $sub_addr_arr[0] == 'west' ) {
        $sub_addr = $sub_addr_arr[1];
      }
      else {
        $sub_addr = $sub_addr_arr[0];
      }
      if ( preg_match( '/\b' . $sub_addr . '\b/i', $locum_addr ) ) {
        $correct++;
      }
      else {
        $error[] = t( 'The street name you entered does not appear to match what we have on file.' );
      }
    }
    else {
      $error[] = t( 'You did not provide a street name.' );
    }
    $numreq++;
  }

  if ( variable_get( 'sopac_require_tel', 1 ) ) {
    if ( trim( $form_state['values']['telephone'] ) ) {
      $locum_tel = ereg_replace( "[^A-Za-z0-9 ]", "", trim( strtolower( $userinfo['tel1'] . ' ' . $userinfo['tel2'] ) ) );
      $sub_tel = ereg_replace( "[^A-Za-z0-9 ]", "", trim( strtolower( $form_state['values']['telephone'] ) ) );
      if ( preg_match( '/\b' . $sub_tel . '\b/i', $locum_tel ) ) {
        $correct++;
      }
      else {
        $error[] = t( 'The telephone number you entered does not appear to match what we have on file.' );
      }
    }
    else {
      $error[] = t( 'You did not provide a telephone number.' );
    }
    $numreq++;
  }

  if ( $req_cfg == 'one' ) {
    if ( $correct > 0 ) {
      $validated = TRUE;
    }
  }
  else {
    if ( $correct == $numreq ) {
      $validated = TRUE;
    }
  }

  if ( count( $error ) && !$validated ) {
    foreach ( $error as $errkey => $errmsg ) {
      form_set_error( $errkey, t( $errmsg ) );
    }
  }

  if ( $validated ) {
    db_query( "INSERT INTO {sopac_card_verify} VALUES ($uid, '$cardnum', '$pnum', 1, NOW())" );
  }

}
