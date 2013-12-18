<?php print $required_hidden_fields ?>
<span style="float:right">Sort by: Title | Author | Checkout Date</span>
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
