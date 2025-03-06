# ozw672-plugin
Pluggin for OZW672 in Loxberry

## Overview

The **OZW672 Plugin** for **LoxBerry** allows you to read data from an **OZW672** device and publish it via **MQTT**. The plugin provides configuration options for both the connection to the OZW672 and the MQTT broker.

## Installation

1. Download and install the plugin via the LoxBerry Plugin Manager.
2. After installation, the plugin can be accessed through the LoxBerry menu.

## Configuration

The plugin contains three tabs:

### 1. **Status**

On this page, you can:

- View the status of the OZW672 service.
- Check log files for troubleshooting and monitoring.

### 2. **OZW672 Settings**

Here you configure the connection to the **OZW672 device**:

- **Host**: The IP address or hostname of the OZW672.
- **Username**: The username for access.
- **Password**: The password for authentication.
- **Cron Time**: The interval for fetching data (e.g., every minute or every 5 minutes).
- **Parameters**: Specify which parameters to read from the OZW672.

### 3. **MQTT Settings**

Here you configure the MQTT connection:

- **Host**: The IP address or hostname of the MQTT broker.
- **Port**: The port of the MQTT broker (default 1883 or 8883 for TLS).
- **Username**: The username for the MQTT broker (optional).
- **Password**: The password for the MQTT broker (optional).
- **Topic**: The MQTT topic where the data will be published.

## Usage

- After configuration, save the settings and start the service via the **Status** page.
- The plugin periodically retrieves data from the OZW672 and publishes it via MQTT.
- Use an MQTT client (such as **MQTT Explorer**) to verify the transmitted data.

## Log Files and Troubleshooting

- Check the **Status** page for errors or warnings.
- Review log files in `/var/log/plugins/ozw672.log` for details.
- Ensure the correct network connection and login credentials are used.

## Support

For support or questions, visit the [LoxBerry Forum](https://www.loxforum.com/) or refer to the documentation on the LoxBerry website.

---

© 2025 OZW672 Plugin Development Team