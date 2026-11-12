<?php
// Prevent directory listing on hosts that use index.php as an index document.
http_response_code(403);
exit;
