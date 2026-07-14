<?php
// Redirect bare /quickbill/backup/ directory access to the app route
header('Location: /quickbill/?url=backup');
exit;
