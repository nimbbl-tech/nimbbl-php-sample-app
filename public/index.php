<?php
require __DIR__ . '/../bootstrap.php';

use Nimbbl\Api\Log\Logger;
use Nimbbl\Api\Common\SdkConstants;

// Constants
define('DEFAULT_TAX_RATE', 0.5);
define('MIN_AMOUNT', 0.01);

// Helper functions
/**
 * Validate email format
 * 
 * @param string $email Email address to validate
 * @return bool True if valid, false otherwise
 */
function validateEmail(string $email): bool
{
  if (empty($email)) {
    return true; // Empty is allowed (will use defaults)
  }
  return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate mobile number (10 digits for India)
 * 
 * @param string $mobile Mobile number to validate
 * @return bool True if valid, false otherwise
 */
function validateMobile(string $mobile): bool
{
  if (empty($mobile)) {
    return true; // Empty is allowed (will use defaults)
  }
  return preg_match('/^\d{10}$/', $mobile) === 1;
}

$orderToken = null;
$orderId = null;
$error = null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Initialize sanitized variables for display (will be set from POST if available)
$name = '';
$email = '';
$mobile = '';
$amount = 4.00; // Default amount
$currency = 'INR';
$mode = 'popup';
$paymentMode = 'allpayment';
$subPaymentMode = '';
$headerCustomisation = '';

if ($method === 'POST') {
  $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;
  // Sanitize currency - only allow valid values
  $currencyRaw = $_POST['currency'] ?? 'INR';
  $allowedCurrencies = ['INR', 'USD', 'CAD', 'EUR'];
  $currency = in_array($currencyRaw, $allowedCurrencies) ? $currencyRaw : 'INR';

  // Get raw user inputs for validation
  $nameRaw = trim($_POST['name'] ?? '');
  $emailRaw = trim($_POST['email'] ?? '');
  $mobileRaw = trim($_POST['mobile'] ?? '');

  // Sanitize mode - only allow valid values
  $modeRaw = $_POST['mode'] ?? 'popup';
  $allowedModes = ['popup', 'redirect'];
  $mode = in_array($modeRaw, $allowedModes) ? $modeRaw : 'popup';

  $orderLineItems = isset($_POST['order_line_items']) ? true : false;
  $renderDesktopUI = isset($_POST['render_desktop_ui']) ? true : false;
  $enableAddressCOD = isset($_POST['enable_address_cod']) ? true : false;

  // Sanitize payment mode values
  $paymentModeRaw = $_POST['payment_customisation'] ?? 'allpayment';
  $allowedPaymentModes = ['allpayment', 'net_banking', 'wallet', 'card', 'upi', 'emi'];
  $paymentMode = in_array($paymentModeRaw, $allowedPaymentModes) ? $paymentModeRaw : 'allpayment';

  $subPaymentModeRaw = $_POST['sub_payment_mode'] ?? '';
  $headerCustomisationRaw = $_POST['header_customisation'] ?? '';

  // Always sanitize inputs for display (even if validation fails, preserve user input)
  $name = htmlspecialchars($nameRaw, ENT_QUOTES, 'UTF-8');
  $email = htmlspecialchars($emailRaw, ENT_QUOTES, 'UTF-8');
  $mobile = htmlspecialchars($mobileRaw, ENT_QUOTES, 'UTF-8');
  $subPaymentMode = htmlspecialchars($subPaymentModeRaw, ENT_QUOTES, 'UTF-8');
  $headerCustomisation = htmlspecialchars($headerCustomisationRaw, ENT_QUOTES, 'UTF-8');

  // Validate inputs (using raw values)
  if ($amount < MIN_AMOUNT) {
    $error = 'Amount must be at least ' . MIN_AMOUNT;
  } elseif (!empty($emailRaw) && !validateEmail($emailRaw)) {
    $error = 'Invalid email format';
  } elseif (!empty($mobileRaw) && !validateMobile($mobileRaw)) {
    $error = 'Invalid mobile number. Please enter 10 digits.';
  } else {
    try {
      // SDK automatically generates and uses merchant token for authentication
      // SDK automatically encrypts payload if ENCRYPT_PAYLOAD flag is enabled
      // Encryption is handled in Orders.createOrder based on the flag passed during SDK initialization

      // Amounts derived strictly from the entered amount
      $totalAmount = (double) $amount;

      // Default user values when inputs are empty
      $userFirstName = !empty($nameRaw) ? $nameRaw : 'John';
      $userEmail = !empty($emailRaw) ? $emailRaw : 'customer@example.com';
      $userMobile = !empty($mobileRaw) ? $mobileRaw : '9876543210';

      // Build Sonic JS apiHost from api_host (scheme://host[:port])
      // Example api_host: https://qa4api.nimbbl.tech -> apiHost: https://qa4api.nimbbl.tech
      function getSonicApiHostFromApiHost(array $config): ?string
      {
        $apiHost = $config['api_host'] ?? null;
        if (!is_string($apiHost) || trim($apiHost) === '') {
          return null;
        }
        $parts = parse_url($apiHost);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
          return null;
        }
        $host = $parts['host'];
        $port = isset($parts['port']) ? (':' . $parts['port']) : '';
        return $parts['scheme'] . '://' . $host . $port;
      }

      // Determine callback_url based on mode
      $callbackUrl = '';
      if ($mode === 'redirect') {
        // Get protocol and host
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $callbackUrl = $protocol . '://' . $host . '/payment-callback.php';
      }

      // Note: Webhooks are configured in Nimbbl Dashboard, not per-order
      // To set up webhooks:
      // 1. Deploy webhook.php to a publicly accessible HTTPS endpoint
      // 2. Configure the webhook URL in Nimbbl Dashboard or contact support@nimbbl.tech
      // 3. Webhooks will be sent to your configured URL automatically

      // Build order line items
      $orderLineItemsArray = [
        [
          'title' => 'Paper Plane',
          'description' => 'Demo product for testing',
          'quantity' => 1,
          'rate' => $totalAmount,
          'total_amount' => $totalAmount,
          'amount_before_tax' => $totalAmount,
          'tax' => 0
        ]
      ];

      // Build order request
      $orderRequest = [
        'total_amount' => $totalAmount,
        'amount_before_tax' => $totalAmount,
        'tax' => 0,
        'currency' => $currency ?: 'INR',
        'name' => $userFirstName,
        'email' => $userEmail,
        'mobile' => $userMobile,
        'user' => [
          'first_name' => $userFirstName,
          'last_name' => 'Doe',
          'email' => $userEmail,
          'country_code' => '+91',
          'mobile_number' => $userMobile
        ],
        'merchant_order_id' => 'demo_' . time(),
        'invoice_id' => 'inv_' . time(),
        'order_line_items' => $orderLineItemsArray,
      ];
      if (!empty($callbackUrl)) {
        $orderRequest['callback_url'] = $callbackUrl;
      }

      // SDK automatically generates and uses merchant token for authentication
      $order = $api->order->createOrder($orderRequest);

      $orderToken = $order['token'] ?? null;
      $orderId = $order['id'] ?? null;
      if (!$orderToken) {
        throw new \Exception('Order token not returned');
      }
    } catch (\Throwable $e) {
      $error = $e->getMessage();
      Logger::getInstance()->error("Order creation error: " . $e->getMessage());
    }
  }
}

// Handle redirect callback
$redirectCallback = isset($_GET['redirect_callback']) && $_GET['redirect_callback'] == '1';
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Nimbbl Sonic Checkout Demo</title>
  <link rel="stylesheet" href="/assets/css/styles.css" />
</head>

<body>
  <header class="header">
    <div class="logo">
      <img src="/assets/img/SonicLogo.svg" alt="Nimbbl Sonic" height="32" />
      <span>by nimbbl.</span>
    </div>
    <div class="nav-links">
      <a href="https://nimbbl.biz/get-in-touch" target="_blank" rel="noreferrer">contact sales</a>
      <a href="https://nimbbl.biz/" target="_blank" rel="noreferrer">visit website →</a>
    </div>
  </header>

  <div class="container">
    <?php if ($error): ?>
      <div class="error-box">
        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <?php if ($orderId && $orderToken): ?>
      <div class="success-box">
        <strong>Order created:</strong> <?php echo htmlspecialchars($orderId); ?><br>
        <small>Checkout will launch automatically...</small>
      </div>
    <?php endif; ?>

    <div class="main-grid">
      <div class="panel">
        <img class="hero-img" src="/assets/img/PaperPlane.png" alt="Paper Plane" />
        <div class="thumbs">
          <div>
            <img src="/assets/img/Nose.png" alt="nose" />
            <div class="thumb-label">nose</div>
          </div>
          <div>
            <img src="/assets/img/Wingpic.png" alt="wing" />
            <div class="thumb-label">wing</div>
          </div>
          <div>
            <img src="/assets/img/Tail.png" alt="tail" />
            <div class="thumb-label">tail</div>
          </div>
        </div>
      </div>

      <div class="panel">
        <form method="POST" action="/index.php">
          <div class="label-row">
            <h1 class="product-title">Paper Plane.</h1>
            <div class="price-box">
              <select name="currency" id="currency_select">
                <option value="INR" <?php echo ($currency === 'INR') ? 'selected' : ''; ?>>INR</option>
                <option value="USD" <?php echo ($currency === 'USD') ? 'selected' : ''; ?>>USD</option>
                <option value="CAD" <?php echo ($currency === 'CAD') ? 'selected' : ''; ?>>CAD</option>
                <option value="EUR" <?php echo ($currency === 'EUR') ? 'selected' : ''; ?>>EUR</option>
              </select>
              <div class="divider"></div>
              <input name="amount" type="number" step="0.01"
                value="<?php echo htmlspecialchars($amount > 0 ? number_format($amount, 2, '.', '') : '4.00', ENT_QUOTES, 'UTF-8'); ?>"
                min="0.01" required />
            </div>
          </div>

          <div class="info-row">
            <img src="/assets/img/InfoIcon.svg" alt="info" />
            <p>this is a real transaction, any amount deducted will refunded within 7 working days</p>
          </div>
          <div class="info-row">
            <img src="/assets/img/InfoIcon.svg" alt="info" />
            <p>you'll have to add amount greater than 2500 to perform EMI transaction.</p>
          </div>

          <div class="section">
            <div class="switch-row">
              <h3>Order line items and personalised payment options</h3>
              <label class="switch">
                <input type="checkbox" name="order_line_items" id="order_line_items" checked />
                <span class="slider"></span>
              </label>
            </div>
          </div>

          <div class="section" id="view_mode_section">
            <div class="switch-row">
              <h3>View mode</h3>
              <div class="view-mode-toggle" id="view_mode_toggle">
                <img src="/assets/img/Smartphone.svg" alt="Mobile" height="24" width="24"
                  style="z-index:20; pointer-events: none;" />
                <img src="/assets/img/MonitorIcon.svg" alt="Desktop" height="24" width="24"
                  style="z-index:20; pointer-events: none;" />
                <input type="checkbox" id="view_toggle" name="render_desktop_ui" class="view-toggle-input" <?php echo isset($_POST['render_desktop_ui']) ? 'checked' : ''; ?> />
                <div class="view-toggle-slider"></div>
              </div>
            </div>
          </div>

          <div class="section">
            <div class="switch-row">
              <h3>Enable Address & COD on Checkout</h3>
              <label class="switch">
                <input type="checkbox" name="enable_address_cod" id="enable_address_cod" />
                <span class="slider"></span>
              </label>
            </div>
          </div>

          <div class="section" id="header_customisation_section">
            <h3>Header customisation</h3>
            <div id="header_customisation_content">
              <!-- Hidden select for form submission -->
              <select class="select-full" name="header_customisation" id="header_customisation" style="display:none;">
                <option value="1">your brand name and brand logo</option>
                <option value="2">your brand logo</option>
              </select>
              <!-- Custom dropdown with icons -->
              <div class="custom-dropdown" id="custom_header_dropdown">
                <div class="custom-dropdown-toggle" id="custom_header_toggle">
                  <div class="custom-dropdown-selected">
                    <img src="/assets/img/DarkBlueBulleticon.svg" alt="your brand name and brand logo" />
                    <span class="custom-dropdown-option-text">your brand name and brand logo</span>
                  </div>
                </div>
                <div class="custom-dropdown-menu" id="custom_header_menu">
                  <!-- Options will be populated dynamically -->
                </div>
              </div>
            </div>
          </div>

          <div class="section" id="payment_customisation_section">
            <h3>Payment customisation</h3>
            <!-- Hidden select for form submission -->
            <select class="select-full" name="payment_customisation" id="payment_customisation" style="display:none;">
              <option value="allpayment">all payments modes</option>
              <option value="net_banking">netbanking</option>
              <option value="wallet">wallet</option>
              <option value="card">card</option>
              <option value="upi">upi</option>
              <option value="emi">emi</option>
            </select>
            <!-- Custom dropdown with icons -->
            <div class="custom-dropdown" id="custom_payment_dropdown">
              <div class="custom-dropdown-toggle" id="custom_payment_toggle">
                <div class="custom-dropdown-selected">
                  <img src="/assets/img/BankLogo.svg" alt="all payments modes" />
                  <span class="custom-dropdown-option-text">all payments modes</span>
                </div>
              </div>
              <div class="custom-dropdown-menu" id="custom_payment_menu">
                <!-- Options will be populated dynamically -->
              </div>
            </div>
            <div id="sub_payment_mode_section" style="display:none; margin-top:32px;">
              <p
                style="font-family: 'Gordita-Medium', 'Gordita', sans-serif; font-size: 14px; color: rgba(0,0,0,0.8); margin-bottom: 16px; line-height: 1.5;">
                subpayment mode</p>
              <!-- Hidden select for form submission -->
              <select class="select-full" name="sub_payment_mode" id="sub_payment_mode" style="display:none;">
                <!-- Options will be populated dynamically -->
              </select>
              <!-- Custom dropdown with icons -->
              <div class="custom-dropdown" id="custom_sub_payment_dropdown">
                <div class="custom-dropdown-toggle" id="custom_sub_payment_toggle">
                  <div class="custom-dropdown-selected">
                    <span class="custom-dropdown-option-text">Select option</span>
                  </div>
                </div>
                <div class="custom-dropdown-menu" id="custom_sub_payment_menu">
                  <!-- Options will be populated dynamically -->
                </div>
              </div>
            </div>
          </div>

          <div class="section">
            <h3>Checkout experience</h3>
            <div class="radio-row">
              <label>
                <input type="radio" name="mode" value="popup" <?php echo ($mode === 'popup') ? 'checked' : ''; ?> />
                pop-up
              </label>
              <label>
                <input type="radio" name="mode" value="redirect" <?php echo ($mode === 'redirect') ? 'checked' : ''; ?> />
                redirect
              </label>
            </div>
          </div>

          <div class="section">
            <div class="checkbox-row">
              <?php
              $prefillUserChecked = isset($_POST['prefill_user']) || !empty($_POST['name']) || !empty($_POST['email']) || !empty($_POST['mobile']);
              ?>
              <input type="checkbox" name="prefill_user" id="prefill_user" <?php echo $prefillUserChecked ? 'checked' : ''; ?> />
              <label for="prefill_user" style="cursor:pointer;">
                <h3 style="margin:0; display:inline;">User details ?</h3>
              </label>
            </div>
            <div id="user_fields"
              style="display:<?php echo $prefillUserChecked ? 'block' : 'none'; ?>; margin-top:12px;">
              <input type="text" name="name" placeholder="Name"
                value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                style="width:100%; padding:8px; margin-bottom:8px; border:1px solid #e4e7ed; border-radius:8px;" />
              <input type="tel" name="mobile" placeholder="Mobile"
                value="<?php echo htmlspecialchars($mobile, ENT_QUOTES, 'UTF-8'); ?>"
                style="width:100%; padding:8px; margin-bottom:8px; border:1px solid #e4e7ed; border-radius:8px;" />
              <input type="email" name="email" placeholder="Email"
                value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                style="width:100%; padding:8px; border:1px solid #e4e7ed; border-radius:8px;" />
            </div>
          </div>

          <button type="submit" class="pay-btn">Pay now</button>
        </form>
      </div>
    </div>
  </div>

  <footer class="footer">
    <div class="footer-left">
      © 2025 nimbbl by bigital technologies pvt ltd
    </div>
    <div class="footer-right">
      <a href="#">privacy</a>
      <a href="#">terms and conditions</a>
      <a href="#">about us</a>
    </div>
  </footer>

  <script>
    // State management
    let orderLineItems = true;
    let enableAddressCOD = false;
    let renderDesktopUI = false;

    // Toggle user fields
    document.getElementById('prefill_user').addEventListener('change', function () {
      document.getElementById('user_fields').style.display = this.checked ? 'block' : 'none';
    });

    // Handle Order Line Items toggle
    const orderLineItemsCheckbox = document.getElementById('order_line_items');
    orderLineItems = orderLineItemsCheckbox.checked;
    orderLineItemsCheckbox.addEventListener('change', function () {
      orderLineItems = this.checked;
      updateAllStates(); // Rebuild header customisation on toggle
    });

    // Handle Enable Address & COD toggle
    const enableAddressCODCheckbox = document.getElementById('enable_address_cod');
    enableAddressCOD = enableAddressCODCheckbox.checked;
    enableAddressCODCheckbox.addEventListener('change', function () {
      enableAddressCOD = this.checked;
      updateAllStates();
    });

    // View mode toggle handler
    const viewToggle = document.getElementById('view_toggle');
    const viewModeToggle = document.getElementById('view_mode_toggle');
    if (viewToggle && viewModeToggle) {
      renderDesktopUI = viewToggle.checked;

      // Handle checkbox change
      viewToggle.addEventListener('change', function () {
        renderDesktopUI = this.checked;
        // Don't update header customisation when view mode changes
        // updateAllStates(); // Removed to prevent header customisation from reacting
      });

      // Make entire toggle container clickable
      viewModeToggle.addEventListener('click', function (e) {
        // Prevent double-toggling if clicking directly on checkbox
        if (e.target !== viewToggle) {
          e.preventDefault();
          viewToggle.checked = !viewToggle.checked;
          viewToggle.dispatchEvent(new Event('change'));
        }
      });
    }

    function updateViewModeState() {
      // View mode should always remain enabled, regardless of Address COD state
      const viewModeSection = document.getElementById('view_mode_section');
      const viewModeToggle = document.getElementById('view_mode_toggle');
      const viewToggle = document.getElementById('view_toggle');

      viewModeSection.classList.remove('disabled');
      viewModeToggle.classList.remove('disabled');
      viewToggle.disabled = false;
    }

    function updateAllStates() {
      // Update Order Line Items state
      const orderLineItemsSection = document.querySelector('#order_line_items').closest('.section');
      if (enableAddressCOD) {
        orderLineItemsCheckbox.disabled = true;
        orderLineItemsSection.classList.add('disabled');
      } else {
        orderLineItemsCheckbox.disabled = false;
        orderLineItemsSection.classList.remove('disabled');
      }

      // Update View Mode state
      updateViewModeState();

      // Update Payment Customisation state
      const paymentSection = document.getElementById('payment_customisation_section');
      const paymentSelect = document.getElementById('payment_customisation');
      if (enableAddressCOD) {
        paymentSection.classList.add('disabled');
        paymentSelect.disabled = true;
        subPaymentSection.style.display = 'none';
        subPaymentSelect.innerHTML = '';
      } else {
        paymentSection.classList.remove('disabled');
        paymentSelect.disabled = false;
      }

      // Update Header Customisation state
      const headerSection = document.getElementById('header_customisation_section');
      const headerContent = document.getElementById('header_customisation_content');
      const headerSelect = document.getElementById('header_customisation');
      const headerToggle = document.getElementById('custom_header_toggle');
      const headerMenu = document.getElementById('custom_header_menu');
      const headerSelected = headerToggle ? headerToggle.querySelector('.custom-dropdown-selected') : null;

      // Get current value before clearing (if it exists)
      let currentValue = null;
      if (headerSelect) {
        currentValue = headerSelect.value;
      }

      // Clear and rebuild header customisation based on state
      headerContent.innerHTML = '';

      if (enableAddressCOD) {
        // Show merchant dropdown when Address COD is enabled
        const hiddenSelect = document.createElement('select');
        hiddenSelect.className = 'select-full';
        hiddenSelect.name = 'header_customisation';
        hiddenSelect.id = 'header_customisation';
        hiddenSelect.style.display = 'none';
        hiddenSelect.innerHTML = `
          <option value="5">MustBuy</option>
          <option value="6">BallMart</option>
          <option value="7">TripKart</option>
        `;
        headerContent.appendChild(hiddenSelect);

        // Create custom dropdown
        const customDropdown = document.createElement('div');
        customDropdown.className = 'custom-dropdown';
        customDropdown.id = 'custom_header_dropdown';
        customDropdown.innerHTML = `
          <div class="custom-dropdown-toggle" id="custom_header_toggle">
            <div class="custom-dropdown-selected">
              <span class="custom-dropdown-option-text">MustBuy</span>
            </div>
          </div>
          <div class="custom-dropdown-menu" id="custom_header_menu"></div>
        `;
        headerContent.appendChild(customDropdown);

        initCustomDropdown('custom_header_dropdown', 'custom_header_toggle', 'custom_header_menu', 'header_customisation', HEADER_MERCHANT_MODES, false, '5');
      } else if (!orderLineItems) {
        // Show disabled state with "your brand name" when orderLineItems is false
        const disabledDiv = document.createElement('div');
        disabledDiv.className = 'select-full';
        disabledDiv.style.cssText = 'padding:10px 12px; border:1px solid rgba(0,0,0,0.1); border-radius:10px; background:#f9f9f9; color:rgba(0,0,0,0.6); cursor:not-allowed;';
        disabledDiv.innerHTML = '<span>your brand name</span>';
        headerContent.appendChild(disabledDiv);
        // Add hidden input for form submission
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'header_customisation';
        hiddenInput.value = '3';
        headerContent.appendChild(hiddenInput);
      } else if (renderDesktopUI) {
        // Show disabled state with "your brand name" when desktop UI is enabled
        const disabledDiv = document.createElement('div');
        disabledDiv.className = 'select-full';
        disabledDiv.style.cssText = 'padding:10px 12px; border:1px solid rgba(0,0,0,0.1); border-radius:10px; background:#f9f9f9; color:rgba(0,0,0,0.6); cursor:not-allowed;';
        disabledDiv.innerHTML = '<span>your brand name</span>';
        headerContent.appendChild(disabledDiv);
        // Add hidden input for form submission
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'header_customisation';
        hiddenInput.value = '4';
        headerContent.appendChild(hiddenInput);
      } else {
        // Show dropdown with options when orderLineItems is true and mobile view
        const hiddenSelect = document.createElement('select');
        hiddenSelect.className = 'select-full';
        hiddenSelect.name = 'header_customisation';
        hiddenSelect.id = 'header_customisation';
        hiddenSelect.style.display = 'none';
        hiddenSelect.innerHTML = `
          <option value="1">your brand name and brand logo</option>
          <option value="2">your brand logo</option>
        `;
        headerContent.appendChild(hiddenSelect);

        // Create custom dropdown
        const customDropdown = document.createElement('div');
        customDropdown.className = 'custom-dropdown';
        customDropdown.id = 'custom_header_dropdown';
        customDropdown.innerHTML = `
          <div class="custom-dropdown-toggle" id="custom_header_toggle">
            <div class="custom-dropdown-selected">
              <img src="/assets/img/DarkBlueBulleticon.svg" alt="your brand name and brand logo" />
              <span class="custom-dropdown-option-text">your brand name and brand logo</span>
            </div>
          </div>
          <div class="custom-dropdown-menu" id="custom_header_menu"></div>
        `;
        headerContent.appendChild(customDropdown);

        initCustomDropdown('custom_header_dropdown', 'custom_header_toggle', 'custom_header_menu', 'header_customisation', HEADER_MODES, true, '1');
      }

      // Recalculate payment options in case currency/state changed
      updatePaymentOptions();
    }

    // Header customisation constants with icon paths
    const HEADER_MODES = [
      { value: '1', label: 'your brand name and brand logo', icon: '/assets/img/DarkBlueBulleticon.svg' },
      { value: '2', label: 'your brand logo', icon: '/assets/img/LighBlueBulletIcon.svg' }
    ];

    const HEADER_MERCHANT_MODES = [
      { value: '5', label: 'MustBuy', icon: null }, // No icons for merchant options
      { value: '6', label: 'BallMart', icon: null },
      { value: '7', label: 'TripKart', icon: null }
    ];

    // Payment mode constants with icon paths
    // Matching React app icons with custom SVG files
    const PAYMENT_MODES = [
      { value: 'allpayment', label: 'all payments modes', icon: '/assets/img/AppsRounded.svg' }, // GrAppsRounded in React
      { value: 'net_banking', label: 'netbanking', icon: '/assets/img/BankLine.svg' }, // RiBankLine in React
      { value: 'wallet', label: 'wallet', icon: '/assets/img/Wallet.svg' }, // TbWallet in React
      { value: 'card', label: 'card', icon: '/assets/img/CreditCard.svg' }, // TbCreditCard in React
      { value: 'upi', label: 'upi', icon: '/assets/img/upiIcon.svg' }, // UpiIcon SVG
      { value: 'emi', label: 'emi', icon: '/assets/img/CreditCardMaterial.svg' } // MdCreditCard in React
    ];

    const NB_MODES = [
      { value: '', label: 'all banks', icon: '/assets/img/AppsRounded.svg' }, // GrAppsRounded in React
      { value: 'hdfc', label: 'hdfc bank', icon: '/assets/img/hdfc.svg' },
      { value: 'sbi', label: 'sbi', icon: '/assets/img/sbi.svg' },
      { value: 'kotak', label: 'kotak bank', icon: '/assets/img/kotak.svg' }
    ];

    const WALLET_MODES = [
      { value: '', label: 'all wallets', icon: '/assets/img/AppsRounded.svg' }, // GrAppsRounded in React
      { value: 'freecharge', label: 'freecharge', icon: '/assets/img/FreeCharge.svg' },
      { value: 'jio_money', label: 'jio money', icon: '/assets/img/JioMoney.svg' },
      { value: 'phonepe', label: 'phonepe', icon: '/assets/img/phonePay.svg' }
    ];

    const UPI_MODES = [
      { value: '', label: 'collect + intent', icon: '/assets/img/upiIcon.svg' },
      { value: 'collect', label: 'collect', icon: '/assets/img/upiIcon.svg' },
      { value: 'intent', label: 'intent', icon: '/assets/img/upiIcon.svg' }
    ];

    const EMI_MODES = [
      { value: '', label: 'all emis', icon: '/assets/img/AppsRounded.svg' }, // GrAppsRounded in React
      { value: 'debit', label: 'debit card emi', icon: '/assets/img/CreditCard1.svg' }, // CiCreditCard1 in React
      { value: 'credit', label: 'credit card emi', icon: '/assets/img/CreditCard2.svg' }, // CiCreditCard2 in React
      { value: 'cardless', label: 'cardless emi', icon: '/assets/img/CreditCardOff.svg' } // CiCreditCardOff in React
    ];

    // Payment UI elements
    const subPaymentSection = document.getElementById('sub_payment_mode_section');
    const subPaymentSelect = document.getElementById('sub_payment_mode');
    const currencySelect = document.querySelector('select[name="currency"]');
    const paymentSelect = document.getElementById('payment_customisation');

    function updatePaymentOptions() {
      const currency = currencySelect.value;
      const currentValue = paymentSelect.value;

      // Clear existing options
      paymentSelect.innerHTML = '';

      // reset sub payment when currency toggles
      subPaymentSection.style.display = 'none';
      subPaymentSelect.innerHTML = '';

      if (currency === 'INR') {
        // Show all payment modes for INR
        const options = [
          { value: 'allpayment', text: 'all payments modes' },
          { value: 'net_banking', text: 'netbanking' },
          { value: 'wallet', text: 'wallet' },
          { value: 'card', text: 'card' },
          { value: 'upi', text: 'upi' },
          { value: 'emi', text: 'emi' }
        ];
        options.forEach(opt => {
          const option = document.createElement('option');
          option.value = opt.value;
          option.textContent = opt.text;
          if (opt.value === currentValue || (!currentValue && opt.value === 'allpayment')) {
            option.selected = true;
          }
          paymentSelect.appendChild(option);
        });
        if (!enableAddressCOD) {
          paymentSelect.disabled = false;
          paymentSelect.style.opacity = '1';
          paymentSelect.style.cursor = 'pointer';
        }
      } else {
        // Show only card for non-INR currencies
        const option = document.createElement('option');
        option.value = 'card';
        option.textContent = 'card';
        option.selected = true;
        paymentSelect.appendChild(option);
        paymentSelect.disabled = true;
        paymentSelect.style.opacity = '0.5';
        paymentSelect.style.cursor = 'not-allowed';
        // ensure subpayment hidden for non-INR
        subPaymentSection.style.display = 'none';
        subPaymentSelect.innerHTML = '';
      }
    }

    // Reusable function to initialize custom dropdown
    function initCustomDropdown(dropdownId, toggleId, menuId, selectId, options, hasIcons = false, defaultValue = null) {
      const dropdown = document.getElementById(dropdownId);
      const toggle = document.getElementById(toggleId);
      const menu = document.getElementById(menuId);
      const select = document.getElementById(selectId);
      const selected = toggle.querySelector('.custom-dropdown-selected');

      if (!dropdown || !toggle || !menu || !select) return;

      // Close dropdown when clicking outside
      document.addEventListener('click', function (event) {
        if (!dropdown.contains(event.target)) {
          menu.classList.remove('open');
        }
      });

      // Toggle dropdown
      toggle.addEventListener('click', function (e) {
        if (this.disabled) return;
        e.stopPropagation();
        menu.classList.toggle('open');
      });

      // Function to populate dropdown
      function populate(options, hasIcons) {
        menu.innerHTML = '';

        if (options.length === 0) {
          toggle.disabled = true;
          return;
        }

        toggle.disabled = false;

        options.forEach((opt) => {
          // Create option for hidden select
          const selectOption = document.createElement('option');
          selectOption.value = opt.value;
          selectOption.textContent = opt.label;
          select.appendChild(selectOption);

          // Create custom dropdown option
          const customOption = document.createElement('div');
          customOption.className = 'custom-dropdown-option';
          customOption.setAttribute('data-value', opt.value);

          if (hasIcons && opt.icon) {
            const img = document.createElement('img');
            img.src = opt.icon;
            img.alt = opt.label;
            customOption.appendChild(img);
          }

          const text = document.createElement('span');
          text.className = 'custom-dropdown-option-text';
          text.textContent = opt.label;
          customOption.appendChild(text);

          customOption.addEventListener('click', function (e) {
            e.stopPropagation();
            const value = this.getAttribute('data-value');
            select.value = value;

            // Trigger change event on select for other listeners
            select.dispatchEvent(new Event('change', { bubbles: true }));

            // Update selected display
            selected.innerHTML = '';
            if (hasIcons && opt.icon) {
              const selectedImg = document.createElement('img');
              selectedImg.src = opt.icon;
              selectedImg.alt = opt.label;
              selected.appendChild(selectedImg);
            }
            const selectedText = document.createElement('span');
            selectedText.className = 'custom-dropdown-option-text';
            selectedText.textContent = opt.label;
            selected.appendChild(selectedText);

            // Update option states
            menu.querySelectorAll('.custom-dropdown-option').forEach(opt => {
              opt.classList.remove('selected');
            });
            this.classList.add('selected');

            menu.classList.remove('open');
          });

          menu.appendChild(customOption);
        });

        // Set default value if provided
        if (defaultValue !== null) {
          const defaultOption = menu.querySelector(`[data-value="${defaultValue}"]`);
          if (defaultOption) {
            defaultOption.click();
          }
        } else if (options.length > 0) {
          // Select first option by default
          const firstOption = menu.querySelector('.custom-dropdown-option');
          if (firstOption) {
            firstOption.click();
          }
        }
      }

      // Initialize with options
      populate(options, hasIcons);

      return { populate };
    }

    // Custom dropdown for sub payment mode
    const customDropdown = document.getElementById('custom_sub_payment_dropdown');
    const customToggle = document.getElementById('custom_sub_payment_toggle');
    const customMenu = document.getElementById('custom_sub_payment_menu');
    const customSelected = customToggle ? customToggle.querySelector('.custom-dropdown-selected') : null;

    // Set up click handler for sub payment dropdown toggle
    if (customToggle && customMenu) {
      // Close dropdown when clicking outside
      document.addEventListener('click', function (event) {
        if (customDropdown && !customDropdown.contains(event.target)) {
          customMenu.classList.remove('open');
        }
      });

      // Toggle dropdown
      customToggle.addEventListener('click', function (e) {
        if (this.disabled) return;
        e.stopPropagation();
        customMenu.classList.toggle('open');
      });
    }

    // Function to populate sub payment dropdown
    function populateSubPaymentDropdown(options, hasIcons = false) {
      if (!customMenu || !customSelected || !subPaymentSelect) return;

      customMenu.innerHTML = '';
      customSelected.innerHTML = '<span class="custom-dropdown-option-text">Select option</span>';
      subPaymentSelect.innerHTML = '';

      if (options.length === 0) {
        if (customToggle) customToggle.disabled = true;
        return;
      }

      if (customToggle) customToggle.disabled = false;

      options.forEach((opt) => {
        // Create option for hidden select
        const selectOption = document.createElement('option');
        selectOption.value = opt.value;
        selectOption.textContent = opt.label;
        subPaymentSelect.appendChild(selectOption);

        // Create custom dropdown option
        const customOption = document.createElement('div');
        customOption.className = 'custom-dropdown-option';
        customOption.setAttribute('data-value', opt.value);

        if (hasIcons && opt.icon) {
          const img = document.createElement('img');
          img.src = opt.icon;
          img.alt = opt.label;
          customOption.appendChild(img);
        }

        const text = document.createElement('span');
        text.className = 'custom-dropdown-option-text';
        text.textContent = opt.label;
        customOption.appendChild(text);

        customOption.addEventListener('click', function (e) {
          e.stopPropagation();
          const value = this.getAttribute('data-value');
          subPaymentSelect.value = value;

          // Update selected display
          customSelected.innerHTML = '';
          if (hasIcons && opt.icon) {
            const selectedImg = document.createElement('img');
            selectedImg.src = opt.icon;
            selectedImg.alt = opt.label;
            customSelected.appendChild(selectedImg);
          }
          const selectedText = document.createElement('span');
          selectedText.className = 'custom-dropdown-option-text';
          selectedText.textContent = opt.label;
          customSelected.appendChild(selectedText);

          // Update option states
          customMenu.querySelectorAll('.custom-dropdown-option').forEach(opt => {
            opt.classList.remove('selected');
          });
          this.classList.add('selected');

          customMenu.classList.remove('open');
        });

        customMenu.appendChild(customOption);
      });

      // Select first option by default if it's empty value
      if (options.length > 0 && options[0].value === '') {
        const firstOption = customMenu.querySelector('.custom-dropdown-option');
        if (firstOption) firstOption.click();
      }
    }

    // Handle payment mode change
    paymentSelect.addEventListener('change', function () {
      const selectedMode = this.value;

      if (selectedMode === 'net_banking') {
        subPaymentSection.style.display = 'block';
        populateSubPaymentDropdown(NB_MODES, true);
      } else if (selectedMode === 'wallet') {
        subPaymentSection.style.display = 'block';
        populateSubPaymentDropdown(WALLET_MODES, true);
      } else if (selectedMode === 'upi') {
        subPaymentSection.style.display = 'block';
        populateSubPaymentDropdown(UPI_MODES, true);
      } else if (selectedMode === 'emi') {
        subPaymentSection.style.display = 'block';
        populateSubPaymentDropdown(EMI_MODES, true);
      } else {
        subPaymentSection.style.display = 'none';
        if (customToggle) customToggle.disabled = true;
      }
    });

    // Initialize custom dropdown state
    if (subPaymentSection.style.display === 'none') {
      if (customToggle) customToggle.disabled = true;
    }

    // Initialize payment customisation dropdown
    initCustomDropdown('custom_payment_dropdown', 'custom_payment_toggle', 'custom_payment_menu', 'payment_customisation', PAYMENT_MODES, true, 'allpayment');

    // Update payment options when currency changes
    currencySelect.addEventListener('change', updatePaymentOptions);

    // Initialize states on page load (includes payment options)
    updateAllStates();

    // Payment response handling (matching React app behavior)
    window.decodedResponse = null;

    function showPaymentResponse(response) {
      // Handle response structure (matching React app)
      const payload = response?.payload || response;
      const status = payload?.status;

      if (!status) {
        console.warn("No status in response", response);
        return;
      }

      // Create or update response notification
      let notification = document.getElementById('payment-response-notification');
      if (!notification) {
        notification = document.createElement('div');
        notification.id = 'payment-response-notification';
        notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 16px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); max-width: 400px; font-family: "Gordita-Medium"; font-size: 14px;';
        document.body.appendChild(notification);
      }

      const isSuccess = status === 'success' || status === 'succeeded';
      notification.style.background = isSuccess ? '#21d29b' : '#ff6b6b';
      notification.style.color = '#fff';

      const orderId = payload?.nimbbl_order_id || '';
      const transactionId = payload?.nimbbl_transaction_id || '';
      const message = payload?.message || (isSuccess ? 'Payment successful!' : 'Payment failed!');

      // Build message matching React app format
      let messageText = message;
      if (orderId) {
        messageText += ' for Order ID: ' + orderId;
      }
      if (transactionId) {
        messageText += ' Transaction ID: ' + transactionId;
      }

      notification.textContent = messageText;
      notification.style.display = 'block';

      // Auto-hide after 10 seconds (matching React app)
      setTimeout(() => {
        if (notification) {
          notification.style.display = 'none';
          window.decodedResponse = null;
        }
      }, 10000);
    }

    // Check for response in URL on page load (for redirect mode)
    (function () {
      const urlParams = new URLSearchParams(window.location.search);
      const responseParam = urlParams.get('response');
      if (responseParam) {
        try {
          const decodedResponse = atob(responseParam);
          const parsedResponse = JSON.parse(decodedResponse);
          window.decodedResponse = parsedResponse;
          showPaymentResponse(parsedResponse);
          // Clear URL parameter
          setTimeout(() => {
            window.history.replaceState({}, document.title, window.location.pathname);
          }, 100);
        } catch (e) {
          console.error("Failed to parse response from URL", e);
        }
      }
    })();
  </script>

  <?php if ($orderToken): ?>
    <?php
    // Build options for checkout launcher (using sanitized values from above)
    $options = [
      'payment_mode_code' => ($paymentMode && $paymentMode !== 'allpayment') ? $paymentMode : '',
    ];

    // Add sub-payment mode based on payment mode (subPaymentMode already sanitized)
    if ($paymentMode === 'net_banking' && $subPaymentMode) {
      $options['bank_code'] = $subPaymentMode;
    } elseif ($paymentMode === 'wallet' && $subPaymentMode) {
      $options['wallet_code'] = $subPaymentMode;
    } elseif ($paymentMode === 'upi' && $subPaymentMode) {
      $options['payment_flow'] = $subPaymentMode;
    } elseif ($paymentMode === 'emi' && $subPaymentMode) {
      $options['emi_code'] = $subPaymentMode;
    }

    // Determine if redirect or popup based on mode (matching React app behavior)
    // $mode already sanitized above
    if ($mode === 'redirect') {
      // Get protocol and host from server variables
      $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
      $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
      $options['callback_url'] = $protocol . '://' . $host . '/payment-callback.php';
    } else {
      // Popup flow with custom handler: redirect to success/failed page after payment
      $options['callback_handler_js'] = 'async function(response) {
                try {
                    // Prevent multiple callback executions
                    if (window.__nimbbl_callback_handled) {
                        return;
                    }
                    if (window.__nimbbl_callback_processing) {
                        return;
                    }
                    window.__nimbbl_callback_processing = true;

                    // Handle null/undefined callback
                    if (!response) {
                        window.location.href = "/payment-failed.php?error=1";
                        return;
                    }

                    // Always POST to backend to normalize/decrypt
                    let decodedResponse = response;
                    try {
                        const encryptedResponse =
                            response?.payload?.encrypted_response ||
                            response?.encrypted_response ||
                            null;

                        const res = await fetch("/payment-callback.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({
                                encrypted_response: encryptedResponse,
                                callback: response
                            })
                        });

                        if (!res.ok) {
                            throw new Error("Payment-callback POST failed: " + res.status);
                        }

                        const data = await res.json();
                        if (data.parsed) {
                            decodedResponse = data.parsed;
                        } else {
                            window.location.href = "/payment-failed.php?error=1";
                            return;
                        }
                    } catch (e) {
                        console.error("Failed to normalize/decrypt callback", e);
                        window.location.href = "/payment-failed.php?error=1";
                        return;
                    }
                    
                    // If another callback already redirected while we were awaiting the server, stop here.
                    if (window.__nimbbl_callback_handled) {
                        return;
                    }
                    
                    // Ensure response has payload structure
                    if (!decodedResponse.payload) {
                        if (decodedResponse.status) {
                            // Wrap in payload structure
                            decodedResponse = { payload: decodedResponse };
                        } else if (!decodedResponse.status && !decodedResponse.payload) {
                            // No status or payload, likely an error - redirect to failed page
                            console.error("Invalid response structure:", decodedResponse);
                            window.location.href = "/payment-failed.php?error=1";
                            return;
                        }
                    }
                    
                    // Extract payment details
                    const payload = decodedResponse.payload || decodedResponse;
                    // Extract status (check transaction/order status too)
                    let status = payload.status || decodedResponse.status;
                    if (!status && payload.transaction && payload.transaction.status) {
                        status = payload.transaction.status;
                    }
                    if (!status && payload.order && payload.order.status) {
                        status = payload.order.status;
                    }
                    if (!status) {
                        status = "failed";
                    }
                    const orderId = payload.nimbbl_order_id || payload.order_id || "";
                    const transactionId = payload.nimbbl_transaction_id || payload.transaction_id || "";
                    const message = payload.message || "";
                    
                    // Encode response as base64 for URL (matching redirect mode)
                    let encodedResponse = "";
                    try {
                        const responseJson = JSON.stringify(decodedResponse);
                        encodedResponse = btoa(responseJson);
                    } catch (e) {
                        console.error("Failed to encode response", e);
                        // If encoding fails, redirect with error flag
                        window.location.href = "/payment-failed.php?error=1";
                        return;
                    }
                    
                    // Redirect to success or failed page based on status
                    // Only redirect to success if status is explicitly "success" or "succeeded"
                    // All other cases (including undefined, null, "failed", etc.) go to failed page
                    if (status === "success" || status === "succeeded" || status === "completed") {
                        console.log("Payment successful, redirecting to success page");
                        window.__nimbbl_callback_handled = true;
                        window.location.href = "/payment-success.php?response=" + encodeURIComponent(encodedResponse) + 
                            (orderId ? "&order_id=" + encodeURIComponent(orderId) : "") +
                            (transactionId ? "&transaction_id=" + encodeURIComponent(transactionId) : "") +
                            (message ? "&message=" + encodeURIComponent(message) : "");
                    } else {
                        console.log("Payment failed or unknown status, redirecting to failed page. Status:", status);
                        window.__nimbbl_callback_handled = true;
                        window.location.href = "/payment-failed.php?response=" + encodeURIComponent(encodedResponse) +
                            (orderId ? "&order_id=" + encodeURIComponent(orderId) : "") +
                            (transactionId ? "&transaction_id=" + encodeURIComponent(transactionId) : "") +
                            (message ? "&message=" + encodeURIComponent(message) : "") +
                            "&status=" + encodeURIComponent(status || "failed");
                    }
                } catch (e) {
                    console.error("Failed to handle callback", e);
                    console.error("Error details:", e.message, e.stack);
                    // Fallback: redirect to failed page
                    window.location.href = "/payment-failed.php?error=1";
                } finally {
                    // If we did not redirect, allow subsequent events to try.
                    if (!window.__nimbbl_callback_handled) {
                        window.__nimbbl_callback_processing = false;
                    }
                }
            }';
    }

    // Optional host overrides for checkout JS (aligns with env-based config used in React demo)
    $checkoutEnv = array_filter([
      'apiHost' => getSonicApiHostFromApiHost($config),
      'checkoutHost' => $config['checkout_host'] ?? null,
    ]);

    echo $checkoutLauncher->renderInlineLauncher($orderToken, $options, $checkoutEnv);
    ?>
  <?php endif; ?>
</body>

</html>