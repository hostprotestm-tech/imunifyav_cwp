#!/bin/bash

#############################################
# ImunifyAV CWP Module - Complete Installer
# Version: 1.0
# Compatible with: AlmaLinux 8/9
#############################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Base directories
CWP_DIR="/usr/local/cwpsrv/htdocs/resources/admin"
MODULE_DIR="${CWP_DIR}/modules"
AJAX_DIR="${CWP_DIR}/addons/ajax"
LANG_DIR="${MODULE_DIR}/language"
LOG_DIR="/var/log/imunifyav_cwp"

# Functions
print_header() {
    echo ""
    echo -e "${BLUE}==========================================${NC}"
    echo -e "${BLUE}   ImunifyAV CWP Module Installer${NC}"
    echo -e "${BLUE}==========================================${NC}"
    echo ""
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${YELLOW}ℹ${NC} $1"
}

# Check root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root"
        exit 1
    fi
}

# Check CWP installation
check_cwp() {
    if [ ! -d "$CWP_DIR" ]; then
        print_error "CWP is not installed or installation directory not found"
        exit 1
    fi
    print_success "CWP installation found"
}

# Install ImunifyAV
install_imunifyav() {
    print_info "Checking ImunifyAV installation..."
    
    if command -v imunify-antivirus &> /dev/null; then
        print_success "ImunifyAV is already installed"
        imunify-antivirus version
    else
        print_info "Installing ImunifyAV Free..."
        
        # Download and run ImunifyAV installer
        cd /tmp
        wget -q https://repo.imunify360.cloudlinux.com/defence360/imav-deploy.sh -O imav-deploy.sh
        chmod +x imav-deploy.sh
        bash imav-deploy.sh --license-free
        
        if command -v imunify-antivirus &> /dev/null; then
            print_success "ImunifyAV installed successfully"
        else
            print_error "ImunifyAV installation failed"
            exit 1
        fi
        
        rm -f /tmp/imav-deploy.sh
    fi
}

# Create module files
create_module_files() {
    print_info "Creating module files..."
    
    # Unlock CWP directory
    chattr -i -R /usr/local/cwpsrv/htdocs/admin 2>/dev/null || true
    
    # Create directories
    mkdir -p "$MODULE_DIR"
    mkdir -p "$AJAX_DIR"
    mkdir -p "$LANG_DIR/en"
    mkdir -p "$LANG_DIR/uk"
    mkdir -p "$LOG_DIR/reports"
    mkdir -p /etc/sysconfig/imunify360
    
    # Create main module file
    cat > "${MODULE_DIR}/imunifyav.php" << 'PHPMODULE'
<?php
/**
 * ImunifyAV Module for CentOS Web Panel
 */

if (!isset($include_path)) {
    echo "invalid access";
    exit();
}

// Simple interface for now - full code would be inserted here
// This is a placeholder that will be replaced with the full module code
?>
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">ImunifyAV Scanner</h3>
    </div>
    <div class="panel-body">
        <p>Module installed successfully. Full interface code needs to be added.</p>
        <p>Please replace this file with the complete module code.</p>
    </div>
</div>
PHPMODULE
    
    # Create AJAX handler
    cat > "${AJAX_DIR}/ajax_imunifyav.php" << 'PHPAJAX'
<?php
/**
 * ImunifyAV AJAX Handler
 */

session_start();
if (!isset($_SESSION['cwp_admin'])) {
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Placeholder for AJAX handler
// Full AJAX code would be inserted here
echo json_encode(['success' => true, 'message' => 'AJAX handler installed']);
?>
PHPAJAX
    
    # Create language files
    cat > "${LANG_DIR}/en/imunifyav.ini" << 'LANGFILE'
TITLE="ImunifyAV Malware Scanner"
SCAN_DIRECTORY="Scan Directory"
START_SCAN="Start Scan"
SCANNING="Scanning in progress..."
SCAN_COMPLETE="Scan Complete"
INFECTED_FILES="Infected Files"
WHITELIST="Whitelist Management"
ADD_TO_WHITELIST="Add to Whitelist"
REMOVE_FROM_WHITELIST="Remove from Whitelist"
EXPORT_REPORT="Export Report"
SCHEDULE_SCAN="Schedule Scan"
SCAN_FREQUENCY="Scan Frequency"
DAILY="Daily"
WEEKLY="Weekly"
MONTHLY="Monthly"
SAVE_SCHEDULE="Save Schedule"
LANGFILE
    
    # Create Ukrainian language file
    cat > "${LANG_DIR}/uk/imunifyav.ini" << 'LANGFILEUK'
TITLE="ImunifyAV Сканер вірусів"
SCAN_DIRECTORY="Каталог для сканування"
START_SCAN="Почати сканування"
SCANNING="Виконується сканування..."
SCAN_COMPLETE="Сканування завершено"
INFECTED_FILES="Інфіковані файли"
WHITELIST="Управління білим списком"
ADD_TO_WHITELIST="Додати до білого списку"
REMOVE_FROM_WHITELIST="Видалити з білого списку"
EXPORT_REPORT="Експортувати звіт"
SCHEDULE_SCAN="Запланувати сканування"
SCAN_FREQUENCY="Частота сканування"
DAILY="Щодня"
WEEKLY="Щотижня"
MONTHLY="Щомісяця"
SAVE_SCHEDULE="Зберегти розклад"
LANGFILEUK
    
    print_success "Module files created"
}

# Add to menu
add_to_menu() {
    print_info "Adding module to CWP menu..."
    
    MENU_FILE="${CWP_DIR}/include/3rdparty.php"
    
    # Check if menu item already exists
    if grep -q "imunifyav" "$MENU_FILE" 2>/dev/null; then
        print_info "Menu item already exists"
    else
        # Add menu item
        echo '<li><a href="index.php?module=imunifyav"><span class="icon16 icomoon-icon-shield"></span>ImunifyAV Scanner</a></li>' >> "$MENU_FILE"
        print_success "Menu item added"
    fi
}

# Set permissions
set_permissions() {
    print_info "Setting permissions..."
    
    # Set ownership
    chown -R cwpsrv:cwpsrv "$MODULE_DIR/imunifyav.php"
    chown -R cwpsrv:cwpsrv "$AJAX_DIR/ajax_imunifyav.php"
    chown -R cwpsrv:cwpsrv "$LANG_DIR/en/imunifyav.ini"
    chown -R cwpsrv:cwpsrv "$LANG_DIR/uk/imunifyav.ini"
    chown -R cwpsrv:cwpsrv "$LOG_DIR"
    
    # Set permissions
    chmod 644 "$MODULE_DIR/imunifyav.php"
    chmod 644 "$AJAX_DIR/ajax_imunifyav.php"
    chmod 644 "$LANG_DIR/en/imunifyav.ini"
    chmod 644 "$LANG_DIR/uk/imunifyav.ini"
    chmod 755 "$LOG_DIR"
    chmod 755 "$LOG_DIR/reports"
    
    # Lock CWP directory
    chattr +i -R /usr/local/cwpsrv/htdocs/admin 2>/dev/null || true
    
    print_success "Permissions set"
}

# Configure ImunifyAV
configure_imunifyav() {
    print_info "Configuring ImunifyAV..."
    
    # Create whitelist file
    if [ ! -f "/etc/sysconfig/imunify360/whitelist.json" ]; then
        echo '{"items": []}' > /etc/sysconfig/imunify360/whitelist.json
    fi
    
    # Create cron for database updates
    cat > /etc/cron.d/imunifyav_update << 'EOF'
# Update ImunifyAV malware database daily
0 3 * * * root /usr/bin/imunify-antivirus update malware-database &> /dev/null
EOF
    
    print_success "ImunifyAV configured"
}

# Create uninstaller
create_uninstaller() {
    print_info "Creating uninstaller..."
    
    cat > /usr/local/bin/uninstall_imunifyav_module.sh << 'UNINSTALLER'
#!/bin/bash

echo "Uninstalling ImunifyAV CWP Module..."

# Remove module files
rm -f /usr/local/cwpsrv/htdocs/resources/admin/modules/imunifyav.php
rm -f /usr/local/cwpsrv/htdocs/resources/admin/addons/ajax/ajax_imunifyav.php
rm -f /usr/local/cwpsrv/htdocs/resources/admin/modules/language/en/imunifyav.ini
rm -f /usr/local/cwpsrv/htdocs/resources/admin/modules/language/uk/imunifyav.ini
rm -rf /var/log/imunifyav_cwp

# Remove menu item
sed -i '/imunifyav/d' /usr/local/cwpsrv/htdocs/resources/admin/include/3rdparty.php 2>/dev/null

# Remove cron jobs
rm -f /etc/cron.d/imunifyav_*

echo "Module uninstalled. To remove ImunifyAV completely, run: imunify-antivirus uninstall"
UNINSTALLER
    
    chmod +x /usr/local/bin/uninstall_imunifyav_module.sh
    print_success "Uninstaller created: /usr/local/bin/uninstall_imunifyav_module.sh"
}

# Download full module code
download_full_code() {
    print_info "Downloading complete module code..."
    
    # URLs for the full module files (you need to host these files somewhere)
    MODULE_URL="https://your-domain.com/imunifyav/imunifyav.php"
    AJAX_URL="https://your-domain.com/imunifyav/ajax_imunifyav.php"
    
    # Check if URLs are placeholder
    if [[ "$MODULE_URL" == *"your-domain"* ]]; then
        print_info "Module files need to be manually updated with full code"
        print_info "Please replace the placeholder files with the complete module code"
        return
    fi
    
    # Download files
    wget -q -O "${MODULE_DIR}/imunifyav.php" "$MODULE_URL" 2>/dev/null && \
        print_success "Main module downloaded" || \
        print_info "Using placeholder module file"
    
    wget -q -O "${AJAX_DIR}/ajax_imunifyav.php" "$AJAX_URL" 2>/dev/null && \
        print_success "AJAX handler downloaded" || \
        print_info "Using placeholder AJAX file"
}

# Restart CWP
restart_cwp() {
    print_info "Restarting CWP services..."
    systemctl restart cwpsrv
    print_success "CWP restarted"
}

# Main installation
main() {
    print_header
    
    print_info "Starting installation process..."
    echo ""
    
    check_root
    check_cwp
    
    echo ""
    read -p "Do you want to install ImunifyAV? (y/n): " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        install_imunifyav
    fi
    
    echo ""
    create_module_files
    add_to_menu
    set_permissions
    configure_imunifyav
    download_full_code
    create_uninstaller
    restart_cwp
    
    echo ""
    print_header
    print_success "Installation completed successfully!"
    echo ""
    print_info "Access the module at:"
    echo -e "${BLUE}http://YOUR_SERVER_IP:2030${NC}"
    echo "Navigate to: ImunifyAV Scanner"
    echo ""
    print_info "To uninstall the module, run:"
    echo "/usr/local/bin/uninstall_imunifyav_module.sh"
    echo ""
    print_info "Note: Replace the module files with the complete code for full functionality"
    echo ""
}

# Run main function
main

exit 0
