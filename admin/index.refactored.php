<?php
// Refactored Admin Entry - modular, logical grouping
// Groups: Core, Studio, Commerce, Users, System
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/View.php';
require_once __DIR__ . '/../lib/SelfCheck.php';

$admin = Auth::requirePanel(Auth::canManageOrders(Auth::user()));
$isAdmin = Auth::isAdmin($admin);

// New logical grouping
$GROUPS = [
  'studio' => ['label'=>'Studio & Generátor', 'tabs'=>['overview','studio','exports','features']],
  'commerce' => ['label'=>'Prodej a peníze', 'tabs'=>['billing','payments','promotions','invoices','codes','orders']],
  'users' => ['label'=>'Uživatelé a podpora', 'tabs'=>['users','messages','activity']],
  'system' => ['label'=>'Systém', 'tabs'=>['settings','accounts','seller','look','mail','adminlog','reset']],
];

// FeaturePackages tab
// Feature toggle check example:
// if (!FeaturePackages::isEnabled('payments')) { hide commerce group }

require_once __DIR__ . '/modules/router.php';
