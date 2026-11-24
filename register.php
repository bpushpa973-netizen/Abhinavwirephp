<?php
// Public self-registration is disabled. Accounts are created by the owner/admin only.
header('Location: login.php?registration=disabled');
exit;
