<?php print $required_hidden_fields ?>
<table cellspacing="0" class="sticky-enabled sticky-table" id="patroninfo">
  <thead class="tableHeader-processed">
    <tr>
      <th>Delete</th>
      <th>Title</th>
      <th>Author</th>
      <th>Checkout Date</th>
    </tr>
  </thead>
  <tbody>
<?php
  $zebra = 'even';
  foreach ($history as $hist_item) {
    $zebra = $zebra == 'odd' ? 'even' : 'odd';
?>
    <tr class="<?php print $zebra ?>">
      <td><?php print $hist_item['delete'] ?></td>
      <td><?php print $hist_item['title_link'] ?></td>
      <td><?php print $hist_item['author'] ?></td>
      <td><?php print $hist_item['codate'] ?></td>
    </tr>
<?php
  }
?>
    <tr class="profile_button <?php print $zebra ?>">
        <?php print $submit ?>
      </td>
    </tr>
  </tbody>
</table>
