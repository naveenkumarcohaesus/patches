diff --git a/src/Service/RestrictIpService.php b/src/Service/RestrictIpService.php
index 4ad9eea..75fcd9b 100644
--- a/src/Service/RestrictIpService.php
+++ b/src/Service/RestrictIpService.php
@@ -360,7 +360,7 @@ class RestrictIpService implements RestrictIpInterface {
         }
       }
     }
-    elseif (isset($this->allRoutes[$restricted_route_name])) {
+    elseif (isset($this->allRoutes) && is_array($this->allRoutes) && isset($this->allRoutes[$restricted_route_name])) {
       // If it's not a regex or a path, set the route name directly in array.
       $route = $this->allRoutes[$restricted_route_name];
 
