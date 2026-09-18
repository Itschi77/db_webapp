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

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
