#!/bin/bash

#############################################
# ImunifyAV Module Test Script
# Tests all module functionality
#############################################

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Counters
TESTS_PASSED=0
TESTS_FAILED=0

# Test results array
declare -a TEST_RESULTS

print_header() {
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}   ImunifyAV Module Test Suite${NC}"
    echo -e "${BLUE}========================================${NC}"
    echo ""
}

test_pass() {
    echo -e "${GREEN}✓${NC} $1"
    TESTS_PASSED=$((TESTS_PASSED + 1))
    TEST_RESULTS+=("PASS: $1")
}

test_fail() {
    echo -e "${RED}✗${NC} $1"
    TESTS_FAILED=$((TESTS_FAILED + 1))
    TEST_RESULTS+=("FAIL: $1")
}

test_info() {
    echo -e "${YELLOW}→${NC} $1"
}

# Test 1: Check ImunifyAV installation
test_imunifyav_installed() {
    test_info "Testing ImunifyAV installation..."
    
    if command -v imunify-antivirus &> /dev/null; then
        VERSION=$(imunify-antivirus version 2>/dev/null | head -n1)
        test_pass "ImunifyAV installed: $VERSION"
    else
        test_fail "ImunifyAV not installed"
        return 1
    fi
    
    # Check if service is running
    if systemctl is-active --quiet imunify-antivirus; then
        test_pass "ImunifyAV service is running"
    else
        test_fail "ImunifyAV service is not running"
    fi
}

# Test 2: Check CWP module files
test_module_files() {
    test_info "Testing module files..."
    
    # Check main module
    if [ -f "/usr/local/cwpsrv/htdocs/resources/admin/modules/imunifyav.php" ]; then
        test_pass "Main module file exists"
    else
        test_fail "Main module file missing"
    fi
    
    # Check AJAX handler
    if [ -f "/usr/local/cwpsrv/htdocs/resources/admin/addons/ajax/ajax_imunifyav.php" ]; then
        test_pass "AJAX handler exists"
    else
        test_fail "AJAX handler missing"
    fi
    
    # Check language files
    if [ -f "/usr/local/cwpsrv/htdocs/resources/admin/modules/language/en/imunifyav.ini" ]; then
        test_pass "English language file exists"
    else
        test_fail "English language file missing"
    fi
    
    # Check menu integration
    if grep -q "imunifyav" "/usr/local/cwpsrv/htdocs/resources/admin/include/3rdparty.php" 2>/dev/null; then
        test_pass "Menu integration found"
    else
        test_fail "Menu integration missing"
    fi
}

# Test 3: Check directories and permissions
test_permissions() {
    test_info "Testing permissions..."
    
    # Check log directory
    if [ -d "/var/log/imunifyav_cwp" ]; then
        test_pass "Log directory exists"
        
        # Check permissions
        PERMS=$(stat -c "%a" /var/log/imunifyav_cwp)
        if [ "$PERMS" = "755" ]; then
            test_pass "Log directory has correct permissions"
        else
            test_fail "Log directory permissions incorrect: $PERMS (should be 755)"
        fi
    else
        test_fail "Log directory missing"
    fi
    
    # Check reports directory
    if [ -d "/var/log/imunifyav_cwp/reports" ]; then
        test_pass "Reports directory exists"
    else
        test_fail "Reports directory missing"
    fi
}

# Test 4: Test ImunifyAV functionality
test_imunifyav_functions() {
    test_info "Testing ImunifyAV functions..."
    
    # Create test directory
    TEST_DIR="/tmp/imunifyav_test_$$"
    mkdir -p "$TEST_DIR"
    
    # Create safe test file
    echo "This is a safe test file" > "$TEST_DIR/test.txt"
    
    # Test scan command
    test_info "Running test scan on $TEST_DIR..."
    SCAN_OUTPUT=$(imunify-antivirus malware on-demand start --path="$TEST_DIR" 2>&1)
    
    if echo "$SCAN_OUTPUT" | grep -q "Task"; then
        test_pass "Scan command works"
    else
        test_fail "Scan command failed"
    fi
    
    # Test malware list command
    if imunify-antivirus malware malicious list --json &>/dev/null; then
        test_pass "Malware list command works"
    else
        test_fail "Malware list command failed"
    fi
    
    # Clean up
    rm -rf "$TEST_DIR"
}

# Test 5: Check whitelist configuration
test_whitelist() {
    test_info "Testing whitelist configuration..."
    
    WHITELIST_FILE="/etc/sysconfig/imunify360/whitelist.json"
    
    if [ -f "$WHITELIST_FILE" ]; then
        test_pass "Whitelist file exists"
        
        # Check if it's valid JSON
        if python3 -m json.tool "$WHITELIST_FILE" &>/dev/null || python -m json.tool "$WHITELIST_FILE" &>/dev/null; then
            test_pass "Whitelist file is valid JSON"
        else
            test_fail "Whitelist file is not valid JSON"
        fi
    else
        test_fail "Whitelist file missing"
    fi
}

# Test 6: Check cron jobs
test_cron_jobs() {
    test_info "Testing cron jobs..."
    
    if [ -f "/etc/cron.d/imunifyav_update" ]; then
        test_pass "Update cron job exists"
    else
        test_fail "Update cron job missing"
    fi
}

# Test 7: Test web interface accessibility
test_web_interface() {
    test_info "Testing web interface..."
    
    # Check if CWP is running
    if systemctl is-active --quiet cwpsrv; then
        test_pass "CWP service is running"
        
        # Test module URL (basic check)
        URL="http://localhost:2030/index.php?module=imunifyav"
        if curl -s -o /dev/null -w "%{http_code}" "$URL" | grep -q "302\|200\|401"; then
            test_pass "Module URL is accessible"
        else
            test_fail "Module URL not accessible"
        fi
    else
        test_fail "CWP service is not running"
    fi
}

# Test 8: Check database connection
test_database() {
    test_info "Testing database configuration..."
    
    if [ -f "/usr/local/cwpsrv/htdocs/resources/admin/include/db_conn.php" ]; then
        test_pass "Database configuration file exists"
    else
        test_fail "Database configuration file missing"
    fi
}

# Test 9: Performance test
test_performance() {
    test_info "Running performance test..."
    
    # Create test directory with multiple files
    PERF_DIR="/tmp/perf_test_$$"
    mkdir -p "$PERF_DIR"
    
    # Create 100 test files
    for i in {1..100}; do
        echo "Test file $i" > "$PERF_DIR/file_$i.txt"
    done
    
    # Time the scan
    START_TIME=$(date +%s)
    imunify-antivirus malware on-demand start --path="$PERF_DIR" --intensity=low &>/dev/null
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    if [ $DURATION -lt 60 ]; then
        test_pass "Performance test completed in ${DURATION}s"
    else
        test_fail "Performance test too slow: ${DURATION}s"
    fi
    
    # Clean up
    rm -rf "$PERF_DIR"
}

# Test 10: Security check
test_security() {
    test_info "Running security checks..."
    
    # Check file ownership
    MODULE_OWNER=$(stat -c "%U:%G" /usr/local/cwpsrv/htdocs/resources/admin/modules/imunifyav.php 2>/dev/null)
    if [ "$MODULE_OWNER" = "cwpsrv:cwpsrv" ]; then
        test_pass "Module has correct ownership"
    else
        test_fail "Module ownership incorrect: $MODULE_OWNER"
    fi
    
    # Check for sensitive data exposure
    if grep -q "db_pass" /usr/local/cwpsrv/htdocs/resources/admin/modules/imunifyav.php 2>/dev/null; then
        test_fail "Potential sensitive data exposure in module"
    else
        test_pass "No sensitive data exposure detected"
    fi
}

# Generate test report
generate_report() {
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}   Test Results Summary${NC}"
    echo -e "${BLUE}========================================${NC}"
    echo ""
    
    echo -e "${GREEN}Passed:${NC} $TESTS_PASSED"
    echo -e "${RED}Failed:${NC} $TESTS_FAILED"
    
    TOTAL=$((TESTS_PASSED + TESTS_FAILED))
    if [ $TOTAL -gt 0 ]; then
        SUCCESS_RATE=$((TESTS_PASSED * 100 / TOTAL))
        echo -e "Success Rate: ${SUCCESS_RATE}%"
    fi
    
    echo ""
    echo "Detailed Results:"
    echo "-----------------"
    for result in "${TEST_RESULTS[@]}"; do
        if [[ $result == PASS* ]]; then
            echo -e "${GREEN}✓${NC} ${result#PASS: }"
        else
            echo -e "${RED}✗${NC} ${result#FAIL: }"
        fi
    done
    
    # Save report to file
    REPORT_FILE="/var/log/imunifyav_cwp/test_report_$(date +%Y%m%d_%H%M%S).txt"
    mkdir -p /var/log/imunifyav_cwp
    {
        echo "ImunifyAV Module Test Report"
        echo "Generated: $(date)"
        echo ""
        echo "Tests Passed: $TESTS_PASSED"
        echo "Tests Failed: $TESTS_FAILED"
        echo "Success Rate: ${SUCCESS_RATE}%"
        echo ""
        echo "Detailed Results:"
        for result in "${TEST_RESULTS[@]}"; do
            echo "$result"
        done
    } > "$REPORT_FILE"
    
    echo ""
    test_info "Report saved to: $REPORT_FILE"
}

# Main execution
main() {
    print_header
    
    # Check if running as root
    if [[ $EUID -ne 0 ]]; then
        echo -e "${RED}This script must be run as root${NC}"
        exit 1
    fi
    
    # Run tests
    test_imunifyav_installed
    test_module_files
    test_permissions
    test_imunifyav_functions
    test_whitelist
    test_cron_jobs
    test_web_interface
    test_database
    test_performance
    test_security
    
    # Generate report
    generate_report
    
    # Exit code based on test results
    if [ $TESTS_FAILED -eq 0 ]; then
        echo ""
        echo -e "${GREEN}All tests passed successfully!${NC}"
        exit 0
    else
        echo ""
        echo -e "${RED}Some tests failed. Please check the report.${NC}"
        exit 1
    fi
}

# Run main function
main
