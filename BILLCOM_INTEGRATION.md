# Bill.com Integration Implementation Summary

## Overview

The Bill.com integration for InvoicePlane has been successfully implemented. This integration allows you to send invoices from InvoicePlane to your Bill.com account via their v3 API, supporting both single invoice and batch submissions. The system automatically handles authentication by logging into Bill.com and managing session tokens.

## What Was Implemented

### 1. Database Changes
**File:** `application/modules/setup/sql/040_billcom_integration.sql`

Added columns to support Bill.com integration:

**Invoice Tracking** (`ip_invoices` table):
- `invoice_billcom_id` - Stores the Bill.com invoice ID (starts with "00e")
- `invoice_billcom_sent_date` - Tracks when the invoice was sent

**Per-Client Control** (`ip_clients` table):
- `client_billcom_enabled` - Toggle Bill.com integration for specific clients

**Action Required:** Run this SQL migration on your database:
```sql
# Bill.com Integration - Add tracking fields to invoices table and client toggle
ALTER TABLE `ip_invoices`
    ADD COLUMN `invoice_billcom_id` VARCHAR(50) DEFAULT NULL AFTER `invoice_url_key`,
    ADD COLUMN `invoice_billcom_sent_date` DATETIME DEFAULT NULL AFTER `invoice_billcom_id`;

# Bill.com Integration - Add per-client enable toggle
ALTER TABLE `ip_clients`
    ADD COLUMN `client_billcom_enabled` TINYINT(1) NOT NULL DEFAULT '0' AFTER `client_active`;
```

### 2. Backend Components

#### Bill.com API Library
**File:** `application/libraries/Billcom.php`

A complete library that handles all Bill.com API communication including:
- **Authentication**: Automatically logs in to Bill.com using username/password to get sessionId
- **Session Management**: Caches sessionId (valid for 35 minutes) and auto-refreshes when expired
- Invoice data mapping from InvoicePlane to Bill.com format
- HTTP requests to Bill.com API with proper authentication headers
- Response handling and error management
- Automatic encryption of sensitive credentials (password and developer key)

#### Controller Methods
**File:** `application/modules/invoices/controllers/Invoices.php`

Two new methods added:
- `send_to_billcom($invoice_id)` - Sends a single invoice to Bill.com (with client enablement check)
- `batch_send_to_billcom()` - Sends multiple selected invoices in batch (with client enablement check)

#### Model Methods
**File:** `application/modules/invoices/models/Mdl_invoices.php`

Three new methods added:
- `mark_sent_to_billcom($invoice_id, $billcom_invoice_id)` - Updates tracking fields
- `get_billcom_status($invoice_id)` - Retrieves Bill.com status for an invoice
- `is_sent_to_billcom($invoice_id)` - Checks if invoice was sent

**File:** `application/modules/clients/models/Mdl_clients.php`

Updated to handle the `client_billcom_enabled` field when saving client data.

### 3. Settings Interface

#### Bill.com Settings Tab
**Files:**
- `application/modules/settings/views/partial_settings_billcom.php`
- `application/modules/settings/views/index.php` (modified)

A new "Bill.com Settings" tab has been added to Settings with fields for:
- Enable/Disable integration toggle
- **Username/Email** (Bill.com account email)
- **Password** (encrypted)
- **Developer Key** (encrypted)
- **Organization ID** (optional)
- **API URL** (Sandbox/Production selector)

### 4. User Interface Features

#### Invoice List Page
**Files:**
- `application/modules/invoices/views/index.php`
- `application/modules/invoices/views/partial_invoice_table.php`

Features added:
- Checkbox column for selecting multiple invoices (when Bill.com is enabled)
- "Select All" checkbox in table header
- "Send Selected to Bill.com" button (appears when invoices are selected)
- "Send to Bill.com" option in each invoice's dropdown menu
- Visual indicators:
  - Green checkmark: Invoice already sent
  - Ban icon + greyed text: Client doesn't have Bill.com enabled
  - Tooltip explaining disabled state

#### Individual Invoice View
**File:** `application/modules/invoices/views/view.php`

Features added:
- "Send to Bill.com" option in the Options dropdown menu
- Shows Bill.com ID with checkmark if invoice was already sent
- Greyed out with ban icon if client doesn't have Bill.com enabled

#### Client Management
**Files:**
- `application/modules/clients/views/form.php`
- `application/modules/clients/views/view.php`

Features added:
- "Enable Bill.com Integration" checkbox in client edit form
- Bill.com status indicator in client view (Enabled/Disabled label)
- Only visible when Bill.com is globally enabled

### 5. Language Support
**File:** `application/language/english/ip_lang.php`

Added translation keys for all Bill.com-related messages and labels, including:
- UI labels and buttons
- Success/error messages
- Authentication messages
- Per-client control messages

## Per-Client Bill.com Control

This integration includes **per-client control** - you can enable or disable Bill.com synchronization for individual clients. This gives you complete flexibility over which customers' invoices are sent to Bill.com.

### How It Works:
- Each client has a **"Enable Bill.com Integration"** checkbox
- Only invoices for clients with this enabled can be sent to Bill.com
- Disabled by default for all clients (safe default)
- You choose which clients use Bill.com
- Visual indicators show when Bill.com is disabled for a client

## How to Set Up and Use

### Prerequisites

Before configuring the integration, you need:

1. A Bill.com account (sandbox or production)
2. Bill.com credentials:
   - Your Bill.com **username/email**
   - Your Bill.com **password**
   - A **Developer Key** from the [Bill.com Developer Portal](https://developer.bill.com/)

### Getting Bill.com API Credentials

1. **Account Credentials**:
   - Your regular Bill.com login email and password
   - These are the same credentials you use to log into Bill.com

2. **Developer Key**:
   - Log in to the [Bill.com Developer Portal](https://developer.bill.com/)
   - Navigate to API Keys section
   - Create or copy your Developer Key

**Note:** The system will automatically handle authentication by:
- Logging in to Bill.com using your credentials
- Obtaining and caching a session ID (valid for 35 minutes)
- Auto-refreshing the session when it expires

### Step 1: Run Database Migration

Execute the SQL migration file to add the necessary columns to your database (both invoice tracking AND client toggle).

### Step 2: Configure Bill.com Credentials

1. Log in to InvoicePlane as an administrator
2. Navigate to **Settings** → **Bill.com Settings** tab
3. Check **"Enable Bill.com Integration"**
4. Enter your credentials:
   - **Username/Email**: Your Bill.com account email
   - **Password**: Your Bill.com account password (will be encrypted)
   - **Developer Key**: Your Bill.com Developer Key from the developer portal (will be encrypted)
   - **Organization ID** (Optional): Leave blank to use your default organization, or specify a Bill.com organization ID
5. Select **API URL**:
   - Use "Sandbox" for testing: `https://gateway.stage.bill.com/connect`
   - Use "Production" for live invoices: `https://gateway.bill.com/connect`
6. Click **Save** button at the bottom

**Important:** Start with the Sandbox environment for testing before moving to Production. Make sure you use sandbox account credentials with the sandbox URL, and production credentials with the production URL.

### Step 3: Enable Bill.com for Specific Clients

**Important:** Before sending invoices, you must enable Bill.com for each client:

1. Go to **Clients** → Select a client
2. Click **Edit**
3. Look for the checkbox **"Enable Bill.com Integration"** (with cloud icon) in the top-right of the Personal Information panel
4. Check the box to enable
5. Click **Save**

Now invoices for this client can be sent to Bill.com.

**Note:** If you try to send an invoice for a client who doesn't have Bill.com enabled, you'll see the error: "Bill.com integration not enabled for this client"

### Step 4: Send Invoices to Bill.com

#### Option A: Send Single Invoice
1. Go to **Invoices** → View any invoice
2. Click the **Options** dropdown (with down arrow)
3. Select **"Send to Bill.com"**
4. The system will automatically log in to Bill.com if needed
5. You'll see a success message with the Bill.com invoice ID
6. A green checkmark will appear next to the option indicating the invoice was sent

#### Option B: Send Multiple Invoices (Batch)
1. Go to **Invoices** list page
2. Check the boxes next to the invoices you want to send
3. Click the **"Send Selected to Bill.com"** button that appears
4. Confirm the action
5. You'll see a summary of successful and failed submissions

#### Option C: From Invoice List Dropdown
1. Go to **Invoices** list page
2. Click **Options** for any invoice
3. Select **"Send to Bill.com"**

## How It Works

### Authentication Flow

1. **Initial Login**:
   - When you first send an invoice, the system calls `POST /v3/login`
   - Sends your username, password, and developer key
   - Receives a `sessionId` from Bill.com

2. **Session Caching**:
   - The `sessionId` is cached in your PHP session
   - Valid for 35 minutes (system refreshes at 30 minutes to be safe)
   - No need to manually manage sessions

3. **Auto-Refresh**:
   - If the session expires, the system automatically logs in again
   - Transparent to the user - no manual intervention needed

4. **API Requests**:
   - All API calls include these headers:
     ```
     Content-Type: application/json
     devKey: {your_developer_key}
     sessionId: {automatically_managed_session_id}
     ```

### Data Mapping

InvoicePlane invoice data is mapped to Bill.com format:

- **Customer Information**:
  - Name: Client name
  - Email: Client email address
  
- **Invoice Line Items**:
  - Quantity: Item quantity
  - Price: Item price
  - Description: Combines item name and description

- **Invoice Metadata**:
  - Invoice Number: InvoicePlane invoice number
  - Invoice Date: Creation date (formatted as YYYY-MM-DD)
  - Due Date: Invoice due date (formatted as YYYY-MM-DD)

- **Processing Options**:
  - `sendEmail`: Set to `false` (prevents Bill.com from auto-sending emails)

## Important Notes

### Per-Client Control

**Why per-client control?**
- Not all clients may need Bill.com integration
- Some clients might use different systems
- Gives you flexibility to choose which invoices sync
- Prevents accidental sends to wrong clients

**Visual Indicators:**
- ✓ Green checkmark: Invoice already sent to Bill.com (shows Bill.com ID)
- ⊘ Ban icon: Client doesn't have Bill.com enabled
- Greyed out text: Option disabled for this client
- Tooltip on hover: Explains why it's disabled

### Invoice Data Mapping

The following data is sent to Bill.com:
- **Customer**: Client name and email (Bill.com will create customer if not exists)
- **Invoice Line Items**: Item quantity, price, and description
- **Invoice Number**: Your InvoicePlane invoice number
- **Invoice Date**: Creation date
- **Due Date**: Invoice due date

### Re-sending Invoices

You can re-send invoices to Bill.com. The system allows this for flexibility, even if an invoice was previously sent. Each send creates a new invoice in Bill.com.

### Status Indicators

- **Green Checkmark** (✓): Invoice has been sent to Bill.com
- **Bill.com ID**: Displayed next to the checkmark (format: 00e...)
- **Ban Icon** (⊘): Client doesn't have Bill.com enabled

### Error Handling

- All errors are logged to `application/logs/`
- User-friendly error messages are displayed via flash notifications
- Common errors include:
  - Missing credentials
  - Authentication failures
  - Invalid invoice data
  - Client not enabled for Bill.com
  - API connectivity issues

### Security

- **Encryption**: Developer Key and Password are encrypted using InvoicePlane's built-in encryption
- **Session Security**: Session IDs are stored in PHP session, not in database
- **Automatic Expiration**: Sessions expire after 35 minutes for security
- **No Plain Text Storage**: Sensitive credentials are never stored in plain text

## Sandbox vs Production

Bill.com provides two separate environments:

### Sandbox Environment
- **Purpose**: Testing and development
- **URL**: `https://gateway.stage.bill.com/connect`
- **Account**: You need to create a separate sandbox account at Bill.com
- **Credentials**: Use your sandbox account username/password
- **Developer Key**: Get from developer portal (may be same for both environments)
- **Data**: Test data only, not visible in production
- **Invoices**: Will appear in your Bill.com sandbox account only
- **Use When**: Setting up integration, testing invoice format, training users

### Production Environment  
- **Purpose**: Live business operations
- **URL**: `https://gateway.bill.com/connect`
- **Account**: Your regular Bill.com business account
- **Credentials**: Your production Bill.com username/password
- **Developer Key**: From developer portal
- **Data**: Real business data
- **Invoices**: Will appear in your live Bill.com account and can be sent to customers
- **Use When**: Ready for live operations after successful sandbox testing

### Important Notes

- Sandbox and production are completely separate environments with separate accounts
- You must use matching credentials and API URL (sandbox credentials with sandbox URL, production with production)
- Developer Keys from the portal work with both environments
- Always test in sandbox first before going to production
- Switch between environments using the dropdown in Bill.com Settings
- When switching environments, update your username/password to match that environment's account

## Troubleshooting

### Common Issues

1. **"Missing credentials" error**:
   - Verify Username, Password, and Developer Key are entered in settings
   - Check that settings were saved properly
   - Ensure credentials are for the correct environment (sandbox vs production)

2. **"Bill.com integration not enabled for this client" message**:
   - The client must have Bill.com integration enabled
   - Edit the client and check the "Enable Bill.com Integration" checkbox
   - Only invoices for enabled clients can be sent to Bill.com

3. **"Login failed" or authentication errors**:
   - Double-check your Bill.com username/email is correct
   - Verify your password (try logging into Bill.com directly to confirm)
   - Ensure Developer Key is valid from the developer portal
   - Check you're using credentials for the correct environment:
     - Sandbox credentials only work with Sandbox API URL
     - Production credentials only work with Production API URL
   - If using multiple Bill.com organizations, specify the Organization ID in settings

4. **"Session expired" messages**:
   - This is normal - the system automatically refreshes
   - If you see repeated session errors, check your credentials

5. **Invoice not appearing in Bill.com**:
   - Check the invoice was marked as sent (green checkmark shows Bill.com ID)
   - Verify you're logged into the correct Bill.com account/organization
   - Look for error messages in InvoicePlane logs (`application/logs/`)
   - Confirm customer data is valid in Bill.com
   - In sandbox mode, invoices appear in your sandbox account, not production

### Debugging Tips

- Check `application/logs/` for detailed error messages
- Look for "Bill.com:" prefix in log entries
- Verify your credentials by logging into Bill.com directly
- Test in sandbox before production
- Use browser developer tools to check for JavaScript errors (for batch sending)

## Technical Details

### Files Modified/Created

**New Files:**
- `application/libraries/Billcom.php` - Main API library
- `application/modules/setup/sql/040_billcom_integration.sql` - Database migration
- `application/modules/settings/views/partial_settings_billcom.php` - Settings UI

**Modified Files:**
- `application/modules/invoices/controllers/Invoices.php` - Added send methods
- `application/modules/invoices/models/Mdl_invoices.php` - Added tracking methods
- `application/modules/invoices/views/index.php` - Added batch UI
- `application/modules/invoices/views/partial_invoice_table.php` - Added checkboxes and indicators
- `application/modules/invoices/views/view.php` - Added send option
- `application/modules/settings/views/index.php` - Added settings tab
- `application/modules/clients/models/Mdl_clients.php` - Handle client toggle
- `application/modules/clients/views/form.php` - Client edit form
- `application/modules/clients/views/view.php` - Client view display
- `application/language/english/ip_lang.php` - Added translations

### API Endpoints Used

- `POST /v3/login` - Authentication (get sessionId)
- `POST /v3/invoices` - Create invoice

### Dependencies

- cURL extension (for HTTP requests)
- InvoicePlane's crypt library (for encryption)
- InvoicePlane's session library (for session storage)

## Testing Checklist

Before going live, test these scenarios:

- [ ] Create a Bill.com sandbox account (separate from production)
- [ ] Get Developer Key from Bill.com Developer Portal
- [ ] Configure sandbox credentials (sandbox username, password, dev key) and save settings
- [ ] Select "Sandbox" as API URL
- [ ] Enable Bill.com for a test client
- [ ] Create a test invoice with multiple line items
- [ ] Send single invoice to Bill.com
- [ ] Check InvoicePlane logs (`application/logs/`) for successful login message
- [ ] Verify invoice appears in Bill.com sandbox with correct:
  - [ ] Customer information
  - [ ] Invoice number
  - [ ] Line items and amounts
  - [ ] Dates (invoice date, due date)
- [ ] Verify green checkmark appears next to "Send to Bill.com" option showing Bill.com ID
- [ ] Test re-sending the same invoice (should work and create new invoice in Bill.com)
- [ ] Create multiple invoices and test batch send
- [ ] Try sending invoice for client without Bill.com enabled (should show error with ban icon)
- [ ] Test session auto-refresh (wait 30+ minutes and send another invoice)
- [ ] Disable Bill.com integration globally and verify options are hidden
- [ ] Re-enable and switch to production:
  - [ ] Change API URL to "Production"
  - [ ] Update credentials to production account username/password
  - [ ] Keep same Developer Key (or use production-specific one if you have it)
  - [ ] Save settings
  - [ ] Send test invoice to verify production connection

## Support and Resources

- **Bill.com API Documentation**: https://developer.bill.com/docs
- **Bill.com Developer Portal**: https://developer.bill.com/
- **InvoicePlane Documentation**: https://wiki.invoiceplane.com/
- **InvoicePlane GitHub**: https://github.com/InvoicePlane/InvoicePlane

## Version Information

- **Implementation Date**: 2024
- **Bill.com API Version**: v3
- **InvoicePlane Version**: 1.6.x compatible
