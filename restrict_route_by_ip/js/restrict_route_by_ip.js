/**
 * @file
 * restrict_route_by_ip.js
 *
 * Provides some client-side functionality for the Restrict route by IP module.
 */

(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.restrictRouteByIp = {
    attach: function (context, settings) {

      $('.restrict-route-edit-form #edit-route').once().on('change', function() {
        var route_name = $(this).val();
        $.ajax({
          url: '/admin/config/system/restrict_route_by_ip/impacted_path',
          method: 'POST',
          data: {
            'route_name': route_name
          },
          success: function (response) {
            if (response.status === 'ok') {
              var paths = response.impacted.join("\n");
              if (paths === '') {
                paths = Drupal.t("No impacted route.");
              }
              $('#edit-impacted-routes').val(paths);
            }
          }
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
