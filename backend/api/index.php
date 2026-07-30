<?php

// Vercel only allows a function's entry point to live under /api, so this
// just hands off to Laravel's normal front controller.
require __DIR__.'/../public/index.php';
