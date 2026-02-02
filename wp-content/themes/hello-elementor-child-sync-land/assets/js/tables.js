jQuery(document).ready(function() {
    var table = jQuery('#pods_table').DataTable({
        "paging": true,
        "lengthChange": false,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true, // Enable responsive behavior
        "dom": "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
               "<'row'<'col-sm-12'tr>>" +
               "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        "language": {
            "paginate": {
                "previous": "<",
                "next": ">"
            }
        },
        "order": [[3, "desc"]], // Initial sort by Date Signed Up
        "columns": [
            null, // Artist
            { "orderable": false }, // Image
            { "type": "num" }, // Albums
            { "type": "date" } // Date Signed Up
        ]
    });
});