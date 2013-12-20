<?php

  print $required_hidden_fields;

//print_r($form['history'][22555]['delete']);

  $uri_arr = explode( '?', request_uri() );
  $uri = $uri_arr[0];
  $get_sort = $form['sort_by'];
  $search_str = $form['search_str'] ? $form['search_str'] : NULL;

  $sort_title = $uri . '?sort=' . (($get_sort == 'title_up') ? 'title_down' : 'title_up');
  $sort_author = $uri . '?sort=' . (($get_sort == 'author_up') ? 'author_down' : 'author_up');
  $sort_date = $uri . '?sort=date_up';

  if ($search_str) {
    $sort_title .= '&search=' . $search_str;
    $sort_author .= '&search=' . $search_str;
    $sort_date .= '&search=' . $search_str;
  }

?>
<span style="float:right">Sort by: <a href="<? print $sort_title ?>">Title</a> | <a href="<? print $sort_author ?>">Author</a> | <a href="<? print $sort_date ?>">Checkout Date</a></span>
<table cellspacing="0" class="sticky-enabled sticky-table" id="patroninfo">
  <thead class="tableHeader-processed">
    <tr>
      <th style="width:10%">Delete</th>
      <th style="width:90%">Item</th>
    </tr>
  </thead>
<form>
  <tbody>
<?php
  $zebra = 'even';
  foreach ($form['history'] as $hist_id => $hist_item) {
    if (is_numeric($hist_id)) {
      $zebra = $zebra == 'odd' ? 'even' : 'odd';
?>
      <tr class="<?php print $zebra ?>">
        <td><?php print drupal_render($form['history'][$hist_id]['delete']) ?></td>
        <td>
          <strong><?php print $hist_item['title_link']['#value'] ?></strong><br />
          <em><?php print $hist_item['author']['#value'] ?></em><br />
          This item was checked out on <?php print $hist_item['codate']['#value'] ?>
        </td>
      </tr>
<?php
    }
  }
  print drupal_render($form['form_token']);
  print drupal_render($form['form_id']);
  print drupal_render($form['form_build_id']);
?>
    <tr class="profile_button">
      <td></td>
      <td>
        <?php print drupal_render($form['submit']) . ' ' . drupal_render($form['deleteall']) ?>
      </td>
    </tr>
  </tbody>
</form>
</table>
