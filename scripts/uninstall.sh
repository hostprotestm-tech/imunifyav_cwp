#!/bin/bash

#############################################
# ImunifyAV CWP Module Uninstaller
# Version: 1.0
#############################################

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${YELLOW}→${NC} $1"
}

# Check root
if [[ $EUID -ne 0 ]]; then
    print_error "This script must be run as root"
    exit 1
fi

echo -e "${RED}╔══════════════════════════════════════════════╗${NC}"
echo -e "${RED}║     ImunifyAV CWP Module Uninstaller        ║${NC}"
echo -e "${RED}╚══════════════════════════════════════════════╝${NC}"
echo ""

echo "This will remove:"
echo "• ImunifyAV CWP Module files"
echo "• Configuration and logs"
echo "• Menu items"
echo "• Scheduled tasks"
echo ""
echo -e "${YELLOW}Note: ImunifyAV itself will NOT be removed${NC}"
echo ""

read -p "Continue with uninstallation? (y/n): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_info "Uninstallation cancelled"
    exit 0
fi

# Create backup before removal
print_info "Creating backup before removal..."
BACKUP_DIR="/root/imunifyav_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup configuration
if [ -f "/etc/sysconfig/imunify360/whitelist.json" ]; then
    cp -p /etc/sysconfig/imunify360/whitelist.json "$BACKUP_DIR/"
fi

if [ -d "/var/log/imunifyav_cwp" ]; then
    cp -rp /var/log/imunifyav_cwp "$BACKUP_DIR/"
fi

print_success "Backup created in: $BACKUP_DIR"

# Unlock CWP directory
chattr -i -R /usr/local/cwpsrv/htdocs/admin 2>/dev/null || true

# Remove module files
print_info "Removing module files..."
rm -f /usr/local/cwpsrv/htdocs/resources/admin/modules/imunifyav.php
rm -f /usr/local/cwpsrv/htdocs/resources/admin/modules/imunifyav_dashboard.php
rm -f /usr/local/cwpsrv/htdocs/resources/admin/addons/ajax/ajax_imunifyav.php
rm -f /usr/local/cwpsrv/htdocs/resources/admin/modules/language/en/imunifyav.ini
rm -f /usr/local/cwpsrv/htdocs/resources/admin/modules/language/uk/imunifyav.ini
rm -f /usr/local/cwpsrv/htdocs/api/api_imunifyav.php
print_success "Module files removed"

# Remove menu item
print_info "Removing menu integration..."
sed -i '/imunifyav/d' /usr/local/cwpsrv/htdocs/resources/admin/include/3rdparty.php 2>/dev/null
print_success "Menu item removed"

# Remove utility scripts
print_info "Removing utility scripts..."
rm -f /usr/local/bin/imunifyav_test
rm -f /usr/local/bin/imunifyav_backup
rm -f /usr/local/bin/imunifyav_uninstall
print_success "Utility scripts removed"

# Remove cron jobs
print_info "Removing scheduled tasks..."
rm -f /etc/cron.d/imunifyav_scan_*
print_success "Scheduled tasks removed"

# Remove logs (optional)
read -p "Remove all logs and reports? (y/n): " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    rm -rf /var/log/imunifyav_cwp
    print_success "Logs removed"
else
    print_info "Logs preserved in /var/log/imunifyav_cwp"
fi

# Lock CWP directory
chattr +i -R /usr/local/cwpsrv/htdocs/admin 2>/dev/null || true

# Ask about ImunifyAV removal
echo ""
read -p "Do you also want to uninstall ImunifyAV? (y/n): " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    print_info "Uninstalling ImunifyAV..."
    if command -v imunify-antivirus &> /dev/null; then
        imunify-antivirus uninstall
        rm -rf /etc/sysconfig/imunify360
        rm -f /etc/cron.d/imunifyav_update
        print_success "ImunifyAV uninstalled"
    else
        print_info "ImunifyAV not found"
    fi
else
    print_info "ImunifyAV preserved"
fi

# Restart CWP
print_info "Restarting CWP..."
systemctl restart cwpsrv

echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║   Uninstallation Completed Successfully!    ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════╝${NC}"
echo ""
echo "Backup saved in: $BACKUP_DIR"
echo ""
