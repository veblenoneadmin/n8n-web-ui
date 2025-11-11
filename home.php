<?php
require "layout.php";

$content = <<<HTML
<div class="bg-white p-8 rounded-xl shadow-sm">
    <h2 class="text-xl font-semibold text-gray-800 mb-2">Welcome!</h2>
    <p class="text-gray-600">This is your dashboard overview page.</p>
</div>
HTML;

renderLayout("Home", $content);
