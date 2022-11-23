

// propagate click on selection checkbox in header row to all rows:
$('.pfy-table-header .pfy-col-1 input[type=checkbox]').click(function () {
  let $table = $(this).closest('.pfy-table-wrapper');
  let val = $(this).prop('checked');
  $('.pfy-table .pfy-col-1 input[type=checkbox]', $table).prop('checked', val);
});

$('#pfy-table-delete-submit').click(function () {
  if (window.confirm(pfyTableDeletePopup)) {
    $(this).closest('form').attr('action','?delete');
    $(this).closest('form').submit();
  }
});
