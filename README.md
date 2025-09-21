# ImunifyAV Module for CentOS Web Panel (CWP)

<div align="center">

**Professional malware scanner integration for CentOS Web Panel**

[Features](#-features) • [Installation](#-quick-installation) • [Documentation](#-documentation) • [API](#-api) • [Support](#-support)

</div>

## 🚀 Quick Installation

### One-Line Installer

Run this command as root on your CWP server:

```bash
wget -O - https://raw.githubusercontent.com/hostprotestm-tech/imunifyav_cwp/main/install.sh | bash
```

Or if you prefer to review the script first:

```bash
wget https://raw.githubusercontent.com/hostprotestm-tech/imunifyav_cwp/main/install.sh
chmod +x install.sh
./install.sh
```

That's it! The installer will:
- ✅ Install ImunifyAV Free
- ✅ Set up the CWP module
- ✅ Configure automatic updates
- ✅ Generate API key
- ✅ Set up all permissions

## ✨ Features

### 🔍 Malware Scanning
- **Quick Scan** - Fast scanning of critical areas
- **Full Scan** - Comprehensive system scanning
- **Custom Scan** - Scan specific directories
- **Real-time Progress** - Live scan progress tracking

### 🛡️ Threat Management
- Clean infected files
- Quarantine dangerous files
- Whitelist management
- Detailed threat reports

### 📅 Automation
- Scheduled scans (daily/weekly/monthly)
- Automatic database updates
- Email notifications (optional)
- REST API for integration

### 📊 Dashboard
- Real-time statistics
- Threat trends visualization
- System health monitoring
- Recent activity tracking

### 🌐 Multi-language Support
- English
- Ukrainian
- Easy to add more languages

## 📋 Requirements

- **OS**: AlmaLinux 8/9, CentOS 7/8, Rocky Linux 8/9
- **Control Panel**: CentOS Web Panel (CWP)
- **Memory**: Minimum 1GB RAM
- **Disk**: 2GB free space
- **Access**: Root privileges

## 🖥️ Usage

### Access the Module

After installation, access your CWP panel:

1. Navigate to `http://YOUR_SERVER_IP:2030`
2. Login with your admin credentials
3. Go to **Security → ImunifyAV Scanner**

### Quick Scan

```bash
# Via command line
imunify-antivirus malware on-demand start --path=/home

# Via API
curl -X POST "http://YOUR_SERVER:2030/api/api_imunifyav.php" \
  -d "action=scan" \
  -d "key=YOUR_API_KEY" \
  -d "path=/home" \
  -d "type=quick"
```

## 🔌 API

The module includes a full REST API for automation:

### Example: Get System Status

```bash
curl "http://YOUR_SERVER:2030/api/api_imunifyav.php?action=status&key=YOUR_API_KEY"
```

### Example: Start Scan

```python
import requests

response = requests.post('http://YOUR_SERVER:2030/api/api_imunifyav.php', data={
    'action': 'scan',
    'key': 'YOUR_API_KEY',
    'path': '/home',
    'type': 'quick'
})
```

[Full API Documentation](docs/API.md)

## 🛠️ Management Commands

After installation, these commands are available:

```bash
# Test module functionality
imunifyav_test

# Backup configuration
imunifyav_backup

# Uninstall module
imunifyav_uninstall
```

## 📁 Repository Structure

```
imunifyav_cwp/
├── install.sh                 # One-line installer
├── scripts/                   # Installation & utility scripts
├── modules/                   # CWP module files
├── api/                      # REST API
├── languages/                # Localization files
└── docs/                     # Documentation
```

## 🔧 Manual Installation

If you prefer manual installation:

```bash
# Clone repository
git clone https://github.com/hostprotestm-tech/imunifyav_cwp.git
cd imunifyav_cwp

# Run installer
chmod +x install.sh
./install.sh
```

## 📊 Screenshots

<details>
<summary>View Screenshots</summary>

### Main Dashboard
![Dashboard](https://via.placeholder.com/800x400?text=Dashboard+Screenshot)

### Scan Interface
![Scanner](https://via.placeholder.com/800x400?text=Scanner+Screenshot)

### Reports
![Reports](https://via.placeholder.com/800x400?text=Reports+Screenshot)

</details>

## 🐛 Troubleshooting

### Module not showing in menu?

```bash
# Check menu file
cat /usr/local/cwpsrv/htdocs/resources/admin/include/3rdparty.php

# Restart CWP
systemctl restart cwpsrv
```

### ImunifyAV not starting?

```bash
# Check service status
systemctl status imunify-antivirus

# Check logs
tail -f /var/log/imunify360/console.log
```

### Permission issues?

```bash
# Fix permissions
chown -R cwpsrv:cwpsrv /var/log/imunifyav_cwp
chmod 755 /var/log/imunifyav_cwp
```

[Full Troubleshooting Guide](docs/TROUBLESHOOTING.md)

## 📝 Documentation

- [Installation Guide](docs/INSTALL.md)
- [API Documentation](docs/API.md)
- [Configuration Guide](docs/CONFIG.md)
- [Changelog](docs/CHANGELOG.md)

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📜 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## ⚠️ Disclaimer

This is an unofficial module for CWP. ImunifyAV is a product of CloudLinux Inc. This module uses the free version of ImunifyAV. For advanced features, consider purchasing ImunifyAV+.

## 💬 Support

- **Issues**: [GitHub Issues](https://github.com/hostprotestm-tech/imunifyav_cwp/issues)
- **Discussions**: [GitHub Discussions](https://github.com/hostprotestm-tech/imunifyav_cwp/discussions)
- **Email**: support@hostprotestm-tech.com

## 🌟 Star History

[![Star History Chart](https://api.star-history.com/svg?repos=hostprotestm-tech/imunifyav_cwp&type=Date)](https://star-history.com/#hostprotestm-tech/imunifyav_cwp&Date)

## 👏 Credits

- Developed by [hostprotestm-tech](https://github.com/hostprotestm-tech)
- ImunifyAV by [CloudLinux](https://www.imunify360.com/)
- CWP by [CentOS Web Panel](http://centos-webpanel.com/)

---

<div align="center">

**If you find this module useful, please ⭐ star the repository!**

Made with ❤️ for the CWP community

</div>
