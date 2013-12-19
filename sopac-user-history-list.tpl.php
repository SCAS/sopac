<?php

  print $required_hidden_fields;

  $uri_arr = explode( '?', request_uri() );
  $uri = $uri_arr[0];
  $get_sort = $data['sort_by'];
  $search_str = $data['search_str'] ? $data['search_str'] : NULL;

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
  <tbody>
<?php
  $zebra = 'even';
  foreach ($data['history'] as $hist_item) {
    $zebra = $zebra == 'odd' ? 'even' : 'odd';
?>
    <tr class="<?php print $zebra ?>">
      <td><?php print $hist_item['delete'] ?></td>
      <td>
        <strong><?php print $hist_item['title_link'] ?></strong><br />
        <em><?php print $hist_item['author'] ?></em><br />
        This item was checked out on <?php print $hist_item['codate'] ?>
      </td>
    </tr>
<?php
  }
?>
    <tr class="profile_button">
      <td></td>
      <td>
        <?php print $data['submit'] . ' ' . $data['deleteall'] ?>
      </td>
    </tr>
  </tbody>
</table>
