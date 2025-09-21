# ImunifyAV Module for CentOS Web Panel - Installation Guide

## Системні вимоги

- CentOS Web Panel (CWP) встановлений та працюючий
- AlmaLinux 8 або AlmaLinux 9
- Root доступ до сервера
- Мінімум 1GB RAM
- 2GB вільного місця на диску

## Крок 1: Встановлення ImunifyAV

1. Завантажте та запустіть скрипт встановлення:

```bash
cd /tmp
wget -O install_imunifyav.sh [URL_TO_SCRIPT]
chmod +x install_imunifyav.sh
./install_imunifyav.sh
```

Скрипт автоматично:
- Визначить версію ОС
- Встановить ImunifyAV Free
- Налаштує базову конфігурацію
- Створить необхідні директорії

## Крок 2: Встановлення модуля CWP

1. Розблокуйте директорію CWP для запису:

```bash
chattr -i -R /usr/local/cwpsrv/htdocs/admin
```

2. Створіть необхідні директорії:

```bash
mkdir -p /usr/local/cwpsrv/htdocs/resources/admin/modules/
mkdir -p /usr/local/cwpsrv/htdocs/resources/admin/modules/language/en/
mkdir -p /usr/local/cwpsrv/htdocs/resources/admin/addons/ajax/
mkdir -p /usr/local/cwpsrv/htdocs/resources/admin/include/
```

3. Завантажте файли модуля:

```bash
# Основний модуль
wget -O /usr/local/cwpsrv/htdocs/resources/admin/modules/imunifyav.php [URL_TO_imunifyav.php]

# AJAX обробник
wget -O /usr/local/cwpsrv/htdocs/resources/admin/addons/ajax/ajax_imunifyav.php [URL_TO_ajax_imunifyav.php]

# Додати пункт меню (додати в кінець файлу)
echo '[CONTENT_OF_3rdparty.php]' >> /usr/local/cwpsrv/htdocs/resources/admin/include/3rdparty.php
```

4. Встановіть правильні права доступу:

```bash
chown -R cwpsrv:cwpsrv /usr/local/cwpsrv/htdocs/resources/admin/modules/imunifyav.php
chown -R cwpsrv:cwpsrv /usr/local/cwpsrv/htdocs/resources/admin/addons/ajax/ajax_imunifyav.php
chmod 644 /usr/local/cwpsrv/htdocs/resources/admin/modules/imunifyav.php
chmod 644 /usr/local/cwpsrv/htdocs/resources/admin/addons/ajax/ajax_imunifyav.php
```

5. Створіть директорії для логів та звітів:

```bash
mkdir -p /var/log/imunifyav_cwp/reports
chmod 755 /var/log/imunifyav_cwp
chown -R cwpsrv:cwpsrv /var/log/imunifyav_cwp
```

6. Заблокуйте директорію CWP знову (для безпеки):

```bash
chattr +i -R /usr/local/cwpsrv/htdocs/admin
```

## Крок 3: Перезапуск CWP

```bash
systemctl restart cwpsrv
```

## Крок 4: Доступ до модуля

1. Відкрийте браузер та перейдіть за адресою:
   ```
   http://YOUR_SERVER_IP:2030
   ```

2. Увійдіть з вашими обліковими даними адміністратора CWP

3. В меню знайдіть розділ "Security" → "ImunifyAV Scanner"

## Використання модуля

### Сканування

1. **Quick Scan (Швидке сканування)**:
   - Перевіряє лише найбільш вразливі місця
   - Швидше, але менш детальне
   - Рекомендується для щоденного використання

2. **Full Scan (Повне сканування)**:
   - Детальна перевірка всіх файлів
   - Займає більше часу
   - Рекомендується щотижня або щомісяця

### Планування сканувань

1. Перейдіть на вкладку "Scheduled Scans"
2. Вкажіть шлях для сканування
3. Виберіть частоту (щодня, щотижня, щомісяця)
4. Виберіть час запуску
5. Натисніть "Save Schedule"

### Управління білим списком

1. Перейдіть на вкладку "Whitelist"
2. Додайте шляхи, які потрібно ігнорувати при скануванні
3. Можете видаляти існуючі записи з білого списку

### Експорт звітів

1. Перейдіть на вкладку "Scan Reports"
2. Знайдіть потрібний звіт
3. Натисніть "Export" для завантаження в текстовому форматі

## Налаштування оновлень

Додайте в crontab для автоматичного оновлення бази даних ImunifyAV:

```bash
echo "0 3 * * * root /usr/bin/imunify-antivirus update malware-database" >> /etc/crontab
```

## Усунення неполадок

### Проблема: Модуль не відображається в меню

Рішення:
```bash
# Перевірте наявність файлу 3rdparty.php
ls -la /usr/local/cwpsrv/htdocs/resources/admin/include/3rdparty.php

# Якщо файл відсутній, створіть його
touch /usr/local/cwpsrv/htdocs/resources/admin/include/3rdparty.php
```

### Проблема: Помилка "ImunifyAV is not installed"

Рішення:
```bash
# Перевірте встановлення ImunifyAV
imunify-antivirus version

# Якщо не встановлено, запустіть скрипт встановлення знову
./install_imunifyav.sh
```

### Проблема: Сканування не запускається

Рішення:
```bash
# Перевірте права доступу
ls -la /var/log/imunifyav_cwp/

# Виправте права доступу
chown -R cwpsrv:cwpsrv /var/log/imunifyav_cwp/
chmod 755 /var/log/imunifyav_cwp/
```

## Деінсталяція

Для видалення модуля виконайте:

```bash
# Видалення файлів модуля
rm -f /usr/local/cwpsrv/htdocs/resources/admin/modules/imunifyav.php
rm -f /usr/local/cwpsrv/htdocs/resources/admin/addons/ajax/ajax_imunifyav.php
rm -rf /var/log/imunifyav_cwp/

# Видалення ImunifyAV (якщо потрібно)
/usr/local/bin/uninstall_imunifyav.sh

# Видалення cron задач
rm -f /etc/cron.d/imunifyav_*
```

## Підтримка

При виникненні проблем:
1. Перевірте логи CWP: `/usr/local/cwpsrv/logs/error_log`
2. Перевірте логи ImunifyAV: `/var/log/imunify360/`
3. Переконайтесь, що всі сервіси працюють:
   ```bash
   systemctl status cwpsrv
   imunify-antivirus version
   ```

## Оновлення модуля

Для оновлення модуля до нової версії:
1. Завантажте нові файли
2. Замініть існуючі файли новими
3. Перезапустіть CWP: `systemctl restart cwpsrv`

## Ліцензія

Цей модуль використовує безкоштовну версію ImunifyAV.
Для розширеного функціоналу розгляньте можливість придбання ImunifyAV+.

---

**Версія модуля**: 1.0  
**Дата оновлення**: 2025
