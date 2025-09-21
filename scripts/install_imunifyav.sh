#!/bin/bash

#############################################
# ImunifyAV Free Installation Script
# Compatible with: AlmaLinux 8/9, CentOS 7/8, Rocky Linux
# Version: 1.0
# GitHub: https://github.com/hostprotestm-tech/imunifyav_cwp
#############################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Functions
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${YELLOW}ℹ${NC} $1"
}

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   print_error "This script must be run as root"
   exit 1
fi

# Detect OS version
detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$NAME
        VER=$VERSION_ID
    else
        print_error "Cannot detect OS version"
        exit 1
    fi
}

# Main installation function
install_imunifyav() {
    print_info "Starting ImunifyAV Free installation..."
    
    # Detect OS
    detect_os
    print_success "Detected OS: $OS $VER"
    
    # Check if already installed
    if command -v imunify-antivirus &> /dev/null; then
        print_info "ImunifyAV is already installed"
        imunify-antivirus version
        return 0
    fi
    
    # Install required packages
    print_info "Installing required packages..."
    if [[ "$OS" == "AlmaLinux" ]] || [[ "$OS" == "CentOS"* ]] || [[ "$OS" == "Rocky Linux" ]]; then
        yum install -y wget curl ca-certificates
    else
        print_error "Unsupported OS: $OS"
        exit 1
    fi
    print_success "Required packages installed"
    
    # Download ImunifyAV installer
    print_info "Downloading ImunifyAV installer..."
    cd /tmp
    wget -q https://repo.imunify360.cloudlinux.com/defence360/imav-deploy.sh -O imav-deploy.sh
    
    if [ ! -f "imav-deploy.sh" ]; then
        print_error "Failed to download ImunifyAV installer"
        exit 1
    fi
    print_success "Installer downloaded"
    
    # Make installer executable
    chmod +x imav-deploy.sh
    
    # Install ImunifyAV Free
    print_info "Installing ImunifyAV Free (this may take a few minutes)..."
    bash imav-deploy.sh --license-free
    
    # Check installation status
    if command -v imunify-antivirus &> /dev/null; then
        print_success "ImunifyAV installed successfully!"
    else
        print_error "ImunifyAV installation failed"
        exit 1
    fi
    
    # Configure ImunifyAV for CWP
    configure_imunifyav
    
    # Clean up
    rm -f /tmp/imav-deploy.sh
    
    print_success "ImunifyAV installation completed!"
}

# Configure ImunifyAV for optimal CWP integration
configure_imunifyav() {
    print_info "Configuring ImunifyAV for CWP..."
    
    # Create config directory if not exists
    mkdir -p /etc/sysconfig/imunify360
    
    # Create basic configuration
    cat > /etc/sysconfig/imunify360/integration.conf << 'EOF'
[paths]
ui_path = /usr/local/cwpsrv/htdocs/resources/admin/modules/imunifyav
panel_info = /usr/local/cwpsrv/htdocs/resources/admin/modules/imunifyav/panel_info.json

[integration_scripts]
panel_name = CWP
EOF
    
    # Create directory for CWP module logs
    mkdir -p /var/log/imunifyav_cwp
    chmod 755 /var/log/imunifyav_cwp
    
    # Create whitelist file
    touch /etc/sysconfig/imunify360/whitelist.json
    echo '{"items": []}' > /etc/sysconfig/imunify360/whitelist.json
    
    # Set up basic malware database update cron
    cat > /etc/cron.d/imunifyav_update << 'EOF'
# Update ImunifyAV malware database daily
0 3 * * * root /usr/bin/imunify-antivirus update malware-database &> /dev/null
EOF
    
    print_success "ImunifyAV configured for CWP"
}

# Create uninstall script
create_uninstaller() {
    cat > /usr/local/bin/uninstall_imunifyav.sh << 'EOF'
#!/bin/bash
echo "Uninstalling ImunifyAV..."
if command -v imunify-antivirus &> /dev/null; then
    imunify-antivirus uninstall
fi
rm -rf /etc/sysconfig/imunify360
rm -f /etc/cron.d/imunifyav_update
rm -rf /var/log/imunifyav_cwp
echo "ImunifyAV uninstalled"
EOF
    chmod +x /usr/local/bin/uninstall_imunifyav.sh
    print_info "Uninstaller created: /usr/local/bin/uninstall_imunifyav.sh"
}

# Main execution
echo "======================================"
echo "   ImunifyAV Free Installation Script"
echo "======================================"
echo ""

install_imunifyav
create_uninstaller

echo ""
echo "======================================"
print_success "Installation completed successfully!"
echo "======================================"
echo ""
print_info "ImunifyAV commands:"
echo "  - Check version: imunify-antivirus version"
echo "  - Start scan: imunify-antivirus malware on-demand start --path=/path/to/scan"
echo "  - Check status: imunify-antivirus malware on-demand status"
echo "  - View infected files: imunify-antivirus malware malicious list"
echo ""
