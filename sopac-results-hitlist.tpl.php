<?php
/*
 * Theme template for SOPAC hitlist
 *
 */

// Prep some stuff here

$new_author_str = sopac_author_format($locum_result['author'], $locum_result['addl_author']);
$url_prefix = variable_get('sopac_url_prefix', 'cat/seek');
if (!$cover_img_url) {
  $cover_img_url = '/' . drupal_get_path('module', 'sopac') . '/images/nocover.png';
}
?>
<div class="hitlist-item">



<table>
  <tr>
  <td class="hitlist-number" width="7%"><?php print $result_num; ?></td>
  <td width="13%">
    <a href="/<?php print $url_prefix . '/record/' . $locum_result['bnum'] ?>">
    <?php
    if (module_exists('covercache')) {
      print $cover_img;
    } else { ?>
      <img class="hitlist-cover" width="72" src="<?php print $cover_img_url; ?>">
    <?php } ?>
    </a>
    </td>
  <td width="<?php print $locum_result['review_links'] ? '50' : '100'; ?>%" valign="top">
    <ul class="hitlist-info">
      <li class="hitlist-title">
        <strong><a href="/<?php print $url_prefix . '/record/' . $locum_result['bnum'] ?>"><?php print $locum_result['title'];?></a></strong>
        <?php if ($locum_result['title_medium']) { print "[$locum_result[title_medium]]"; } ?>
      </li>
      <li><a href="/<?php print $url_prefix . 
        '/search/author/' . 
        urlencode($new_author_str) .
        '">' . $new_author_str; ?></a>
      </li>
      <li><?php print $locum_result['pub_info']; ?></li>
      <?php if ($locum_result['callnum']) { 
        ?><li><?php print t('Call number: '); ?><strong><?php print $locum_result['callnum']; ?></strong></li><?php
      } else if (count($locum_result['avail_details'])) {
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
      print '<li><a href="' . $rev_link . '" target="_new">' . $rev_title . '</a>';
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
