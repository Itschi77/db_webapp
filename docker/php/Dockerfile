FROM php:8.4-cli

# Basis- und Build-Pakete
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    gnupg \
    unixodbc \
    unixodbc-dev \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libreoffice-writer \
    fonts-dejavu-core \
    fonts-liberation \
    openjdk-21-jre-headless \
    && rm -rf /var/lib/apt/lists/*

# Microsoft Repository für Debian 13
RUN curl -sSL -O https://packages.microsoft.com/config/debian/13/packages-microsoft-prod.deb \
    && dpkg -i packages-microsoft-prod.deb \
    && rm packages-microsoft-prod.deb

# Microsoft ODBC Driver 18
RUN apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 \
    && rm -rf /var/lib/apt/lists/*

# Normale PHP-Erweiterungen
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    intl \
    zip

# Microsoft PHP-Treiber für SQL Server
RUN pecl install sqlsrv-5.13.3 pdo_sqlsrv-5.13.3 \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Aktueller KoSIT-Validator für XRechnung 3.0.2 (Stand 31.08.2026)
RUN mkdir -p /opt/kosit-xrechnung/config \
    && curl -fsSL https://github.com/itplr-kosit/validator/releases/download/v1.6.3/validator-1.6.3-standalone.jar -o /opt/kosit-xrechnung/validator.jar \
    && curl -fsSL https://github.com/itplr-kosit/validator-configuration-xrechnung/releases/download/v2026-08-31/xrechnung-3.0.2-validator-configuration-2026-08-31.zip -o /tmp/xrechnung-validator.zip \
    && unzip -q /tmp/xrechnung-validator.zip -d /opt/kosit-xrechnung/config \
    && rm /tmp/xrechnung-validator.zip

# veraPDF 1.30.x für PDF/A-3u-Konformitätsprüfung
RUN mkdir -p /tmp/verapdf-installer /opt/verapdf \
    && curl -fsSL https://software.verapdf.org/rel/1.30/verapdf-greenfield-1.30.2-installer.zip -o /tmp/verapdf-installer.zip \
    && unzip -q /tmp/verapdf-installer.zip -d /tmp/verapdf-installer \
    && VERAPDF_JAR=$(find /tmp/verapdf-installer -name 'verapdf-izpack-installer*.jar' -print -quit) \
    && printf '%s\n' \
       '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' \
       '<AutomatedInstallation langpack="eng">' \
       '<com.izforge.izpack.panels.htmlhello.HTMLHelloPanel id="welcome"/>' \
       '<com.izforge.izpack.panels.target.TargetPanel id="install_dir"><installpath>/opt/verapdf</installpath></com.izforge.izpack.panels.target.TargetPanel>' \
       '<com.izforge.izpack.panels.packs.PacksPanel id="sdk_pack_select">' \
       '<pack index="0" name="veraPDF Mac and *nix Scripts" selected="true"/>' \
       '<pack index="1" name="veraPDF Validation model" selected="true"/>' \
       '<pack index="2" name="veraPDF Documentation" selected="false"/>' \
       '<pack index="3" name="veraPDF Sample Plugins" selected="false"/>' \
       '</com.izforge.izpack.panels.packs.PacksPanel>' \
       '<com.izforge.izpack.panels.install.InstallPanel id="install"/>' \
       '<com.izforge.izpack.panels.finish.FinishPanel id="finish"/>' \
       '</AutomatedInstallation>' > /tmp/verapdf-install.xml \
    && java -jar "$VERAPDF_JAR" /tmp/verapdf-install.xml \
    && test -x /opt/verapdf/verapdf \
    && rm -rf /tmp/verapdf-installer /tmp/verapdf-installer.zip /tmp/verapdf-install.xml

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
