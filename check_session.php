<?php
session_start();

if (!isset($_SESSION['test'])) {
    $_SESSION['test'] = 'working';
    echo "First load: Session set. Reload this page.";
} else {
    echo "Session value: " . $_SESSION['test'];
}
