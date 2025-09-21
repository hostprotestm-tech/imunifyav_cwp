#!/bin/bash

#############################################
# ImunifyAV Module Backup & Restore Script
# Version: 1.0
#############################################

# Configuration
BACKUP_DIR="/backup/imunifyav_module"
MODULE_DIR="/usr/local/cwpsrv/htdocs/resources/admin/modules"
AJAX_DIR="/usr/local/cwpsrv/htdocs/resources/admin/addons/ajax"
LANG_DIR="/usr/local/cwpsrv/htdocs/resources/admin/modules/language"
CONFIG_DIR="/etc/sysconfig/imunify360"
LOG_DIR="/var/log/imunifyav_cwp"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
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

print_header() {
    echo ""
    echo -e "${BLUE}==========================================${NC}"
    echo -e "${BLUE}   $1${NC}"
    echo -e "${BLUE}==========================================${NC}"
    echo ""
}

# Check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root"
        exit 1
    fi
}

# Create backup
create_backup() {
    print_header "ImunifyAV Module Backup"
    
    # Create backup directory
    BACKUP_PATH="${BACKUP_DIR}/backup_${TIMESTAMP}"
    mkdir -p "$BACKUP_PATH"
    
    print_info "Creating backup in: $BACKUP_PATH"
    
    # Backup module files
    print_info "Backing up module files..."
    
    # Main module
    if [ -f "${MODULE_DIR}/imunifyav.php" ]; then
        cp -p "${MODULE_DIR}/imunifyav.php" "${BACKUP_PATH}/"
        print_success "Main module backed up"
    else
        print_error "Main module not found"
    fi
    
    # AJAX handler
    if [ -f "${AJAX_DIR}/ajax_imunifyav.php" ]; then
        cp -p "${AJAX_DIR}/ajax_imunifyav.php" "${BACKUP_PATH}/"
        print_success "AJAX handler backed up"
    else
        print_error "AJAX handler not found"
    fi
    
    # Language files
    mkdir -p "${BACKUP_PATH}/language/en"
    mkdir -p "${BACKUP_PATH}/language/uk"
    
    if [ -f "${LANG_DIR}/en/imunifyav.ini" ]; then
        cp -p "${LANG_DIR}/en/imunifyav.ini" "${BACKUP_PATH}/language/en/"
        print_success "English language file backed up"
    fi
    
    if [ -f "${LANG_DIR}/uk/imunifyav.ini" ]; then
        cp -p "${LANG_DIR}/uk/imunifyav.ini" "${BACKUP_PATH}/language/uk/"
        print_success "Ukrainian language file backed up"
    fi
    
    # Backup configuration
    print_info "Backing up configuration..."
    
    mkdir -p "${BACKUP_PATH}/config"
    
    # Whitelist
    if [ -f "${CONFIG_DIR}/whitelist.json" ]; then
        cp -p "${CONFIG_DIR}/whitelist.json" "${BACKUP_PATH}/config/"
        print_success "Whitelist configuration backed up"
    fi
    
    # Integration config
    if [ -f "${CONFIG_DIR}/integration.conf" ]; then
        cp -p "${CONFIG_DIR}/integration.conf" "${BACKUP_PATH}/config/"
        print_success "Integration configuration backed up"
    fi
    
    # Backup schedules and reports
    print_info "Backing up schedules and reports..."
    
    if [ -d "$LOG_DIR" ]; then
        cp -rp "$LOG_DIR" "${BACKUP_PATH}/logs"
        print_success "Logs and reports backed up"
    fi
    
    # Backup menu integration
    if grep -q "imunifyav" "/usr/local/cwpsrv/htdocs/resources/admin/include/3rdparty.php" 2>/dev/null; then
        grep "imunifyav" "/usr/local/cwpsrv/htdocs/resources/admin/include/3rdparty.php" > "${BACKUP_PATH}/menu_item.txt"
        print_success "Menu integration backed up"
    fi
    
    # Backup cron jobs
    print_info "Backing up cron jobs..."
    mkdir -p "${BACKUP_PATH}/cron"
    
    for cronfile in /etc/cron.d/imunifyav_*; do
        if [ -f "$cronfile" ]; then
            cp -p "$cronfile" "${BACKUP_PATH}/cron/"
            print_success "Cron job backed up: $(basename $cronfile)"
        fi
    done
    
    # Create backup info file
    cat > "${BACKUP_PATH}/backup_info.txt" << EOF
ImunifyAV Module Backup
Created: $(date)
Hostname: $(hostname)
CWP Version: $(cat /usr/local/cwpsrv/htdocs/resources/admin/version 2>/dev/null || echo "Unknown")
ImunifyAV Version: $(imunify-antivirus version 2>/dev/null | head -n1 || echo "Not installed")

Files included:
- Module files
- Language files
- Configuration files
- Schedules and reports
- Menu integration
- Cron jobs
EOF
    
    # Create compressed archive
    print_info "Creating compressed archive..."
    cd "$BACKUP_DIR"
    tar -czf "imunifyav_backup_${TIMESTAMP}.tar.gz" "backup_${TIMESTAMP}"
    
    if [ $? -eq 0 ]; then
        print_success "Backup archive created: ${BACKUP_DIR}/imunifyav_backup_${TIMESTAMP}.tar.gz"
        
        # Calculate size
        SIZE=$(du -h "${BACKUP_DIR}/imunifyav_backup_${TIMESTAMP}.tar.gz" | awk '{print $1}')
        print_info "Backup size: $SIZE"
        
        # Remove uncompressed backup
        rm -rf "${BACKUP_PATH}"
    else
        print_error "Failed to create backup archive"
    fi
}

# List available backups
list_backups() {
    print_header "Available Backups"
    
    if [ ! -d "$BACKUP_DIR" ]; then
        print_info "No backups found"
        return
    fi
    
    BACKUPS=$(ls -1 ${BACKUP_DIR}/imunifyav_backup_*.tar.gz 2>/dev/null)
    
    if [ -z "$BACKUPS" ]; then
        print_info "No backups found"
    else
        echo "Available backups:"
        echo ""
        
        i=1
        for backup in $BACKUPS; do
            SIZE=$(du -h "$backup" | awk '{print $1}')
            DATE=$(basename "$backup" | sed 's/imunifyav_backup_//;s/.tar.gz//')
            echo "  $i. $(basename $backup) (Size: $SIZE)"
            
            # Try to extract and show backup info
            if tar -xzOf "$backup" "backup_${DATE}/backup_info.txt" 2>/dev/null | head -n3 | tail -n2; then
                echo ""
            fi
            
            i=$((i + 1))
        done
    fi
}

# Restore from backup
restore_backup() {
    print_header "ImunifyAV Module Restore"
    
    # List available backups
    list_backups
    
    echo ""
    read -p "Enter backup file name (or full path): " BACKUP_FILE
    
    # Check if file exists
    if [ ! -f "$BACKUP_FILE" ] && [ ! -f "${BACKUP_DIR}/${BACKUP_FILE}" ]; then
        print_error "Backup file not found"
        exit 1
    fi
    
    # Use full path if not provided
    if [ ! -f "$BACKUP_FILE" ]; then
        BACKUP_FILE="${BACKUP_DIR}/${BACKUP_FILE}"
    fi
    
    print_info "Restoring from: $BACKUP_FILE"
    
    # Extract backup
    TEMP_DIR="/tmp/imunifyav_restore_$$"
    mkdir -p "$TEMP_DIR"
    
    tar -xzf "$BACKUP_FILE" -C "$TEMP_DIR"
    
    if [ $? -ne 0 ]; then
        print_error "Failed to extract backup"
        rm -rf "$TEMP_DIR"
        exit 1
    fi
    
    # Find extracted directory
    RESTORE_DIR=$(ls -d ${TEMP_DIR}/backup_* 2>/dev/null | head -n1)
    
    if [ ! -d "$RESTORE_DIR" ]; then
        print_error "Invalid backup structure"
        rm -rf "$TEMP_DIR"
        exit 1
    fi
    
    # Show backup info
    if [ -f "${RESTORE_DIR}/backup_info.txt" ]; then
        echo ""
        print_info "Backup information:"
        cat "${RESTORE_DIR}/backup_info.txt"
        echo ""
    fi
    
    # Confirm restore
    read -p "Do you want to proceed with restore? (y/n): " -n 1 -r
    echo ""
    
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_info "Restore cancelled"
        rm -rf "$TEMP_DIR"
        exit 0
    fi
    
    # Create safety backup of current state
    print_info "Creating safety backup of current state..."
    create_backup
    
    # Unlock CWP directory
    chattr -i -R /usr/local/cwpsrv/htdocs/admin 2>/dev/null || true
    
    # Restore module files
    print_info "Restoring module files..."
    
    if [ -f "${RESTORE_DIR}/imunifyav.php" ]; then
        cp -p "${RESTORE_DIR}/imunifyav.php" "${MODULE_DIR}/"
        chown cwpsrv:cwpsrv "${MODULE_DIR}/imunifyav.php"
        print_success "Main module restored"
    fi
    
    if [ -f "${RESTORE_DIR}/ajax_imunifyav.php" ]; then
        cp -p "${RESTORE_DIR}/ajax_imunifyav.php" "${AJAX_DIR}/"
        chown cwpsrv:cwpsrv "${AJAX_DIR}/ajax_imunifyav.php"
        print_success "AJAX handler restored"
    fi
    
    # Restore language files
    if [ -d "${RESTORE_DIR}/language" ]; then
        cp -rp "${RESTORE_DIR}/language/"* "${LANG_DIR}/"
        chown -R cwpsrv:cwpsrv "${LANG_DIR}/en/imunifyav.ini" 2>/dev/null
        chown -R cwpsrv:cwpsrv "${LANG_DIR}/uk/imunifyav.ini" 2>/dev/null
        print_success "Language files restored"
    fi
    
    # Restore configuration
    print_info "Restoring configuration..."
    
    if [ -d "${RESTORE_DIR}/config" ]; then
        mkdir -p "$CONFIG_DIR"
        cp -p "${RESTORE_DIR}/config/"* "$CONFIG_DIR/" 2>/dev/null
        print_success "Configuration restored"
    fi
    
    # Restore logs and reports
    if [ -d "${RESTORE_DIR}/logs" ]; then
        cp -rp "${RESTORE_DIR}/logs/"* "$LOG_DIR/" 2>/dev/null
        chown -R cwpsrv:cwpsrv "$LOG_DIR"
        print_success "Logs and reports restored"
    fi
    
    # Restore menu integration
    if [ -f "${RESTORE_DIR}/menu_item.txt" ]; then
        if ! grep -q "imunifyav" "/usr/local/cwpsrv/htdocs/resources/admin/include/3rdparty.php" 2>/dev/null; then
            cat "${RESTORE_DIR}/menu_item.txt" >> "/usr/local/cwpsrv/htdocs/resources/admin/include/3rdparty.php"
            print_success "Menu integration restored"
        fi
    fi
    
    # Restore cron jobs
    if [ -d "${RESTORE_DIR}/cron" ]; then
        cp -p "${RESTORE_DIR}/cron/"* /etc/cron.d/ 2>/dev/null
        print_success "Cron jobs restored"
    fi
    
    # Lock CWP directory
    chattr +i -R /usr/local/cwpsrv/htdocs/admin 2>/dev/null || true
    
    # Clean up
    rm -rf "$TEMP_DIR"
    
    # Restart CWP
    print_info "Restarting CWP..."
    systemctl restart cwpsrv
    
    print_success "Restore completed successfully!"
}

# Delete old backups
cleanup_backups() {
    print_header "Cleanup Old Backups"
    
    read -p "Keep backups for how many days? (default: 30): " DAYS
    DAYS=${DAYS:-30}
    
    print_info "Deleting backups older than $DAYS days..."
    
    find "$BACKUP_DIR" -name "imunifyav_backup_*.tar.gz" -mtime +$DAYS -exec rm -f {} \;
    
    print_success "Cleanup completed"
}

# Export configuration only
export_config() {
    print_header "Export Configuration"
    
    CONFIG_EXPORT="/tmp/imunifyav_config_${TIMESTAMP}.tar.gz"
    
    TEMP_DIR="/tmp/config_export_$$"
    mkdir -p "$TEMP_DIR"
    
    # Copy configuration files
    cp -p "${CONFIG_DIR}/whitelist.json" "$TEMP_DIR/" 2>/dev/null
    cp -p "${CONFIG_DIR}/integration.conf" "$TEMP_DIR/" 2>/dev/null
    cp -p "${LOG_DIR}/schedule.json" "$TEMP_DIR/" 2>/dev/null
    
    # Create archive
    cd /tmp
    tar -czf "$CONFIG_EXPORT" -C "$TEMP_DIR" .
    
    rm -rf "$TEMP_DIR"
    
    print_success "Configuration exported to: $CONFIG_EXPORT"
}

# Import configuration
import_config() {
    print_header "Import Configuration"
    
    read -p "Enter configuration file path: " CONFIG_FILE
    
    if [ ! -f "$CONFIG_FILE" ]; then
        print_error "Configuration file not found"
        exit 1
    fi
    
    TEMP_DIR="/tmp/config_import_$$"
    mkdir -p "$TEMP_DIR"
    
    tar -xzf "$CONFIG_FILE" -C "$TEMP_DIR"
    
    if [ $? -eq 0 ]; then
        # Import configuration files
        [ -f "${TEMP_DIR}/whitelist.json" ] && cp -p "${TEMP_DIR}/whitelist.json" "${CONFIG_DIR}/"
        [ -f "${TEMP_DIR}/integration.conf" ] && cp -p "${TEMP_DIR}/integration.conf" "${CONFIG_DIR}/"
        [ -f "${TEMP_DIR}/schedule.json" ] && cp -p "${TEMP_DIR}/schedule.json" "${LOG_DIR}/"
        
        print_success "Configuration imported successfully"
    else
        print_error "Failed to import configuration"
    fi
    
    rm -rf "$TEMP_DIR"
}

# Show menu
show_menu() {
    print_header "ImunifyAV Module Backup & Restore"
    
    echo "1. Create Backup"
    echo "2. Restore from Backup"
    echo "3. List Available Backups"
    echo "4. Export Configuration Only"
    echo "5. Import Configuration"
    echo "6. Cleanup Old Backups"
    echo "7. Exit"
    echo ""
    read -p "Select option [1-7]: " OPTION
    
    case $OPTION in
        1) create_backup ;;
        2) restore_backup ;;
        3) list_backups ;;
        4) export_config ;;
        5) import_config ;;
        6) cleanup_backups ;;
        7) exit 0 ;;
        *) 
            print_error "Invalid option"
            sleep 2
            show_menu
            ;;
    esac
    
    echo ""
    read -p "Press Enter to continue..."
    show_menu
}

# Main execution
main() {
    check_root
    
    # Create backup directory if not exists
    mkdir -p "$BACKUP_DIR"
    
    # Check if argument provided
    if [ $# -eq 0 ]; then
        show_menu
    else
        case $1 in
            backup) create_backup ;;
            restore) restore_backup ;;
            list) list_backups ;;
            export) export_config ;;
            import) import_config ;;
            cleanup) cleanup_backups ;;
            *)
                print_error "Invalid argument: $1"
                echo "Usage: $0 [backup|restore|list|export|import|cleanup]"
                exit 1
                ;;
        esac
    fi
}

# Run main function
main "$@"
