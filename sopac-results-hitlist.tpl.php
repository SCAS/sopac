<?php
/*
 * Theme template for SOPAC hitlist
 *
 */

// Prep some stuff here

$new_author_str = sopac_author_format($locum_result['author'], $locum_result['addl_author']);
$url_prefix = variable_get('sopac_url_prefix', 'cat/seek');

if (!module_exists('covercache')) {
  if (strpos($locum_result['cover_img'], 'http://') !== FALSE) {
    $cover_img = $locum_result['cover_img'];
  }
  else {
    $cover_img = base_path() . drupal_get_path('module', 'sopac') . '/images/nocover.png';
  }
  $cover_img = '<img class="hitlist-cover" width="100" src="' . $cover_img . '">';
  $cover_img = l($cover_img,
                 variable_get('sopac_url_prefix', 'cat/seek') . '/record/' . $locum_result['bnum'],
                 array('html' => TRUE));
}
if ( !count($locum_result['availability']['items'] ) ) {
  $call_number_text = strtoupper($locum_result['callnum']);
} else {
  $callnum_arr = array();
  foreach ($locum_result['availability']['items'] as $locum_item) {
    if (!in_array(trim(strtoupper($locum_item['callnum'])), $callnum_arr)) {
      $callnum_arr[] = trim(strtoupper($locum_item['callnum']));
    }
  }
  if (!count($callnum_arr)) {
    $call_number_text = strtoupper($locum_result['callnum']);
  } else if (count($callnum_arr) == 1) {
    $call_number_text = strtoupper($callnum_arr[0]);
  } else if (count($callnum_arr) > 1) {
    $call_number_text = 'Multiple: ' . implode(', ', $callnum_arr);
  }
}

?>
<div class="hitlist-item">

<table>
  <tr>
  <td class="hitlist-number" width="7%"><?php print $result_num; ?></td>
  <td width="13%"><?php print $cover_img; ?></td>
  <td width="<?php print $locum_result['review_links'] ? '50' : '100'; ?>%" valign="top">
    <ul class="hitlist-info">
      <li class="hitlist-title">
        <strong><?php print l(ucwords($locum_result['title']), $url_prefix . '/record/' . $locum_result['bnum']); ?></strong>
        <?php
        if ($locum_result['title_medium']) {
          print "[$locum_result[title_medium]]";
        }
        ?>
      </li>
      <li>
      <?php
        print l($new_author_str, $url_prefix . '/search/author/' . urlencode($new_author_str));
      ?>
      </li>
      <li><?php print $locum_result['pub_info']; ?></li>
      <?php if ($locum_result['callnum']) {
        ?><li><?php print t('Call number: '); ?><strong><?php print $call_number_text; ?></strong></li><?php
      }
      elseif (count($locum_result['avail_details'])) {
        ?><li><?php print t('Call number: '); ?><strong><?php print key($locum_result['avail_details']); ?></strong></li><?php
      } ?>
      <br />
      <li>
      <?php
      print $locum_result['status']['avail'] . t(' of ') . $locum_result['status']['total'] . ' ';
      print ($locum_result['status']['total'] == 1) ? t('copy available') : t('copies available');
      ?>
      </li>
      <?php
      if (!in_array($locum_result['loc_code'], $no_circ)) {
        print '<li class="item-request"><strong>» ' . sopac_put_request_link($locum_result['bnum']) . '</strong></li>';
      }
      ?>
    </ul>
  </td>
  <?php
  if ($locum_result['review_links']) {
    print '<td width="50%" valign="top">';
    print '<ul class="hitlist-info">';
    print '<li class="hitlist-subtitle">Reviews &amp; Summaries</li>';
    foreach ($locum_result['review_links'] as $rev_title => $rev_link) {
      print '<li>' . l($rev_title, $rev_link, array('attributes' => array('target' => "_new"))) . '</li>';
    }
    print '</ul></td>';
  }
  ?>
  <td width="15%">
  <ul class="hitlist-format-icon">
    <li><img src="<?php print '/' . drupal_get_path('module', 'sopac') . '/images/' . $locum_result['mat_code'] . '.png' ?>"></li>
    <li style="margin-top: -2px;"><?php print wordwrap($locum_config['formats'][$locum_result['mat_code']], 8, '<br />'); ?></li>
  </ul>

  </td>

  </tr>

</table>
</div>
