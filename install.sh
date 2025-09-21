#!/bin/bash

#############################################
# ImunifyAV CWP Module - Master Installer
# GitHub: https://github.com/hostprotestm-tech/imunifyav_cwp
# Version: 1.0
#############################################

set -e

# Configuration
GITHUB_REPO="https://raw.githubusercontent.com/hostprotestm-tech/imunifyav_cwp/main"
INSTALL_DIR="/tmp/imunifyav_install_$$"
CWP_MODULE_DIR="/usr/local/cwpsrv/htdocs/resources/admin/modules"
CWP_AJAX_DIR="/usr/local/cwpsrv/htdocs/resources/admin/addons/ajax"
CWP_LANG_DIR="/usr/local/cwpsrv/htdocs/resources/admin/modules/language"
LOG_FILE="/var/log/imunifyav_install.log"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Logging
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

print_banner() {
    clear
    echo -e "${BLUE}"
    echo "╔══════════════════════════════════════════════╗"
    echo "║     ImunifyAV CWP Module Installer v1.0     ║"
    echo "║  https://github.com/hostprotestm-tech/      ║"
    echo "╚══════════════════════════════════════════════╝"
    echo -e "${NC}"
    echo ""
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
    log "SUCCESS: $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
    log "ERROR: $1"
}

print_info() {
    echo -e "${YELLOW}→${NC} $1"
    log "INFO: $1"
}

# Check root privileges
check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root!"
        echo "Please run: sudo bash $0"
        exit 1
    fi
}

# Check OS compatibility
check_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$NAME
        VER=$VERSION_ID
        
        if [[ "$OS" == "AlmaLinux" ]] && ([[ "$VER" == "8"* ]] || [[ "$VER" == "9"* ]]); then
            print_success "OS Compatible: $OS $VER"
        elif [[ "$OS" == "CentOS"* ]] || [[ "$OS" == "Rocky Linux" ]]; then
            print_info "OS: $OS $VER - Proceeding with installation"
        else
            print_error "Unsupported OS: $OS $VER"
            read -p "Continue anyway? (y/n): " -n 1 -r
            echo
            [[ ! $REPLY =~ ^[Yy]$ ]] && exit 1
        fi
    else
        print_error "Cannot detect OS version"
        exit 1
    fi
}

# Check CWP installation
check_cwp() {
    if [ ! -d "/usr/local/cwpsrv" ]; then
        print_error "CentOS Web Panel is not installed!"
        echo "Please install CWP first: http://centos-webpanel.com"
        exit 1
    fi
    print_success "CWP installation detected"
}

# Create installation directory
setup_install_dir() {
    print_info "Creating temporary installation directory..."
    mkdir -p "$INSTALL_DIR"
    cd "$INSTALL_DIR"
    print_success "Installation directory ready: $INSTALL_DIR"
}

# Download all files from GitHub
download_files() {
    print_info "Downloading files from GitHub repository..."
    
    # Create subdirectories
    mkdir -p scripts modules api languages/en languages/uk docs
    
    # Download scripts
    print_info "Downloading installation scripts..."
    wget -q "$GITHUB_REPO/scripts/install_imunifyav.sh" -O scripts/install_imunifyav.sh
    wget -q "$GITHUB_REPO/scripts/install_module.sh" -O scripts/install_module.sh
    wget -q "$GITHUB_REPO/scripts/test_module.sh" -O scripts/test_module.sh
    wget -q "$GITHUB_REPO/scripts/backup_restore.sh" -O scripts/backup_restore.sh
    wget -q "$GITHUB_REPO/scripts/uninstall.sh" -O scripts/uninstall.sh
    
    # Download module files
    print_info "Downloading module files..."
    wget -q "$GITHUB_REPO/modules/imunifyav.php" -O modules/imunifyav.php
    wget -q "$GITHUB_REPO/modules/ajax_imunifyav.php" -O modules/ajax_imunifyav.php
    wget -q "$GITHUB_REPO/modules/imunifyav_dashboard.php" -O modules/imunifyav_dashboard.php
    wget -q "$GITHUB_REPO/modules/3rdparty.php" -O modules/3rdparty.php
    
    # Download API
    print_info "Downloading API files..."
    wget -q "$GITHUB_REPO/api/api_imunifyav.php" -O api/api_imunifyav.php
    
    # Download language files
    print_info "Downloading language files..."
    wget -q "$GITHUB_REPO/languages/en/imunifyav.ini" -O languages/en/imunifyav.ini
    wget -q "$GITHUB_REPO/languages/uk/imunifyav.ini" -O languages/uk/imunifyav.ini
    
    # Download documentation
    print_info "Downloading documentation..."
    wget -q "$GITHUB_REPO/docs/INSTALL.md" -O docs/INSTALL.md
    wget -q "$GITHUB_REPO/docs/API.md" -O docs/API.md
    wget -q "$GITHUB_REPO/README.md" -O README.md
    
    print_success "All files downloaded successfully"
}

# Install ImunifyAV
install_imunifyav() {
    print_info "Installing ImunifyAV Free..."
    
    # Check if already installed
    if command -v imunify-antivirus &> /dev/null; then
        print_info "ImunifyAV is already installed"
        imunify-antivirus version
        read -p "Reinstall ImunifyAV? (y/n): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            return
        fi
    fi
    
    # Install ImunifyAV
    chmod +x scripts/install_imunifyav.sh
    bash scripts/install_imunifyav.sh
    
    if command -v imunify-antivirus &> /dev/null; then
        print_success "ImunifyAV installed successfully"
    else
        print_error "ImunifyAV installation failed"
        exit 1
    fi
}

# Install CWP module
install_cwp_module() {
    print_info "Installing CWP module files..."
    
    # Unlock CWP directory
    chattr -i -R /usr/local/cwpsrv/htdocs/admin 2>/dev/null || true
    
    # Create directories
    mkdir -p "$CWP_MODULE_DIR"
    mkdir -p "$CWP_AJAX_DIR"
    mkdir -p "$CWP_LANG_DIR/en"
    mkdir -p "$CWP_LANG_DIR/uk"
    mkdir -p /var/log/imunifyav_cwp/reports
    mkdir -p /etc/sysconfig/imunify360
    
    # Copy module files
    cp modules/imunifyav.php "$CWP_MODULE_DIR/"
    cp modules/ajax_imunifyav.php "$CWP_AJAX_DIR/"
    cp modules/imunifyav_dashboard.php "$CWP_MODULE_DIR/"
    
    # Copy language files
    cp languages/en/imunifyav.ini "$CWP_LANG_DIR/en/"
    cp languages/uk/imunifyav.ini "$CWP_LANG_DIR/uk/"
    
    # Copy API
    mkdir -p /usr/local/cwpsrv/htdocs/api
    cp api/api_imunifyav.php /usr/local/cwpsrv/htdocs/api/
    
    # Add menu item
    MENU_FILE="/usr/local/cwpsrv/htdocs/resources/admin/include/3rdparty.php"
    if ! grep -q "imunifyav" "$MENU_FILE" 2>/dev/null; then
        cat modules/3rdparty.php >> "$MENU_FILE"
        print_success "Menu item added"
    else
        print_info "Menu item already exists"
    fi
    
    # Set permissions
    chown -R cwpsrv:cwpsrv "$CWP_MODULE_DIR/imunifyav.php"
    chown -R cwpsrv:cwpsrv "$CWP_MODULE_DIR/imunifyav_dashboard.php"
    chown -R cwpsrv:cwpsrv "$CWP_AJAX_DIR/ajax_imunifyav.php"
    chown -R cwpsrv:cwpsrv "$CWP_LANG_DIR/en/imunifyav.ini"
    chown -R cwpsrv:cwpsrv "$CWP_LANG_DIR/uk/imunifyav.ini"
    chown -R cwpsrv:cwpsrv /var/log/imunifyav_cwp
    chown cwpsrv:cwpsrv /usr/local/cwpsrv/htdocs/api/api_imunifyav.php
    
    chmod 644 "$CWP_MODULE_DIR/imunifyav.php"
    chmod 644 "$CWP_MODULE_DIR/imunifyav_dashboard.php"
    chmod 644 "$CWP_AJAX_DIR/ajax_imunifyav.php"
    chmod 644 /usr/local/cwpsrv/htdocs/api/api_imunifyav.php
    chmod 755 /var/log/imunifyav_cwp
    
    # Lock CWP directory
    chattr +i -R /usr/local/cwpsrv/htdocs/admin 2>/dev/null || true
    
    print_success "CWP module installed successfully"
}

# Configure ImunifyAV
configure_imunifyav() {
    print_info "Configuring ImunifyAV..."
    
    # Create whitelist
    if [ ! -f "/etc/sysconfig/imunify360/whitelist.json" ]; then
        echo '{"items": []}' > /etc/sysconfig/imunify360/whitelist.json
    fi
    
    # Create API keys file
    if [ ! -f "/etc/sysconfig/imunify360/api_keys.json" ]; then
        echo '{"keys": []}' > /etc/sysconfig/imunify360/api_keys.json
        chmod 600 /etc/sysconfig/imunify360/api_keys.json
    fi
    
    # Setup cron for database updates
    cat > /etc/cron.d/imunifyav_update << 'EOF'
# Update ImunifyAV malware database daily at 3 AM
0 3 * * * root /usr/bin/imunify-antivirus update malware-database &> /dev/null
EOF
    
    print_success "ImunifyAV configured"
}

# Copy utility scripts
install_utilities() {
    print_info "Installing utility scripts..."
    
    # Copy scripts to /usr/local/bin
    cp scripts/test_module.sh /usr/local/bin/imunifyav_test
    cp scripts/backup_restore.sh /usr/local/bin/imunifyav_backup
    cp scripts/uninstall.sh /usr/local/bin/imunifyav_uninstall
    
    chmod +x /usr/local/bin/imunifyav_test
    chmod +x /usr/local/bin/imunifyav_backup
    chmod +x /usr/local/bin/imunifyav_uninstall
    
    print_success "Utility scripts installed"
}

# Restart services
restart_services() {
    print_info "Restarting services..."
    
    systemctl restart cwpsrv
    
    if systemctl is-active --quiet cwpsrv; then
        print_success "CWP service restarted successfully"
    else
        print_error "CWP service restart failed"
    fi
}

# Cleanup
cleanup() {
    print_info "Cleaning up temporary files..."
    rm -rf "$INSTALL_DIR"
    print_success "Cleanup completed"
}

# Generate first API key
generate_api_key() {
    print_info "Generating initial API key..."
    
    API_KEY=$(openssl rand -hex 32)
    API_CONFIG="/etc/sysconfig/imunify360/api_keys.json"
    
    cat > "$API_CONFIG" << EOF
{
  "keys": [
    {
      "key": "$API_KEY",
      "name": "Initial Admin Key",
      "created": "$(date -Iseconds)",
      "expires": null,
      "permissions": ["admin"],
      "active": true
    }
  ]
}
EOF
    
    chmod 600 "$API_CONFIG"
    
    echo ""
    echo -e "${GREEN}═══════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}Your API Key: ${YELLOW}$API_KEY${NC}"
    echo -e "${GREEN}═══════════════════════════════════════════════════════${NC}"
    echo "Save this key securely! It has admin permissions."
    echo ""
}

# Show completion message
show_completion() {
    echo ""
    echo -e "${GREEN}╔══════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║     Installation Completed Successfully! ✓          ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${BLUE}Access Information:${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo -e "Web Interface: ${YELLOW}http://$(hostname -I | awk '{print $1}'):2030${NC}"
    echo -e "Module Path: ${YELLOW}Security → ImunifyAV Scanner${NC}"
    echo -e "API Endpoint: ${YELLOW}http://$(hostname -I | awk '{print $1}'):2030/api/api_imunifyav.php${NC}"
    echo ""
    echo -e "${BLUE}Available Commands:${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "• imunifyav_test      - Test module functionality"
    echo "• imunifyav_backup    - Backup/restore configuration"
    echo "• imunifyav_uninstall - Uninstall module"
    echo ""
    echo -e "${BLUE}Quick Test:${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "Run: imunifyav_test"
    echo ""
    echo -e "${BLUE}Documentation:${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "GitHub: https://github.com/hostprotestm-tech/imunifyav_cwp"
    echo ""
    echo "Installation log: $LOG_FILE"
    echo ""
}

# Error handler
error_handler() {
    print_error "An error occurred during installation!"
    echo "Check log file: $LOG_FILE"
    cleanup
    exit 1
}

# Set error trap
trap error_handler ERR

# Main installation flow
main() {
    # Initialize log
    echo "Installation started at $(date)" > "$LOG_FILE"
    
    # Show banner
    print_banner
    
    # Preliminary checks
    print_info "Running preliminary checks..."
    check_root
    check_os
    check_cwp
    
    echo ""
    echo -e "${YELLOW}This installer will:${NC}"
    echo "1. Install ImunifyAV Free antivirus"
    echo "2. Install CWP management module"
    echo "3. Configure automatic malware scanning"
    echo "4. Setup REST API for automation"
    echo ""
    
    read -p "Continue with installation? (y/n): " -n 1 -r
    echo ""
    
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_info "Installation cancelled by user"
        exit 0
    fi
    
    # Installation steps
    setup_install_dir
    download_files
    install_imunifyav
    install_cwp_module
    configure_imunifyav
    install_utilities
    generate_api_key
    restart_services
    cleanup
    
    # Show completion
    show_completion
    
    log "Installation completed successfully"
}

# Run main function
main "$@"
