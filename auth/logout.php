<?php
include_once('../sessions/session.php');


detruireSession();

header('Location: /personnel/signin');
exit;