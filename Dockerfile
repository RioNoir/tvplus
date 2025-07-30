FROM php:8.2-fpm

ARG WORKDIR=/var/www
ENV DOCUMENT_ROOT=${WORKDIR}
ENV CLIENT_MAX_BODY_SIZE=15M
ARG GUID=1000
ARG PUID=1000
ENV USER_NAME=www-data
ARG GROUP_NAME=www-data
ENV TIMEZONE="Europe/Rome"
ENV SP_DATA_PATH=/data
ENV DISPLAY=:99

# Install system dependencies
RUN apt-get clean && apt-get update -y && apt-get install -y \
    wget \
    git \
    build-essential \
    apt-transport-https \
    software-properties-common \
    gnupg \
    curl \
    libfreetype6-dev libjpeg62-turbo-dev libmemcached-dev \
    libzip-dev libpng-dev libonig-dev libxml2-dev librdkafka-dev libpq-dev \
    python3 python3-pip python3-venv \
    nodejs npm \
    openssh-server \
    zip unzip \
    supervisor \
    sqlite3  \
    nano \
    cron \
    nginx

#Xvfb e Chrome dependencies \
RUN apt-get install -y \
    gnupg \
    ca-certificates \
    fonts-liberation \
    libappindicator3-1 \
    libasound2 \
    libatk-bridge2.0-0 \
    libatk1.0-0 \
    libcairo2 \
    libcups2 \
    libdbus-1-3 \
    libexpat1 \
    libfontconfig1 \
    libgdk-pixbuf2.0-0 \
    libglib2.0-0 \
    libgtk-3-0 \
    libnspr4 \
    libnss3 \
    libpangocairo-1.0-0 \
    libxcomposite1 \
    libxdamage1 \
    libxext6 \
    libxfixes3 \
    libxrandr2 \
    libxrender1 \
    libxss1 \
    libxtst6 \
    libxshmfence-dev \
    # Chromium dependencies:
    libgbm-dev \
    # XVFB and other X-server utilities
    xvfb \
    x11-xkb-utils \
    xfonts-base \
    xauth \
    procps \
    # Per eseguire Chrome/Chromium:
    locales \
    gconf-service \
    libasound2 \
    libcurl3-gnutls \
    libgl1-mesa-glx \
    libnspr4 \
    libnss3 \
    libxkbcommon0 \
    libxss1 \
    xdg-utils

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions zip, mbstring, exif, bcmath, intl
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install zip mbstring exif pcntl bcmath -j$(nproc) gd intl pdo_mysql pdo_pgsql opcache sockets

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# --- Installazione di Chromium e ChromeDriver ---

# Step 1: Installazione delle dipendenze di sistema per Chromium.
# Questi pacchetti sono essenziali per far funzionare Chromium in un ambiente headless Linux.
RUN apt-get update && apt-get install -y \
    wget \
    gnupg \
    unzip \
    jq \
    libnss3 \
    libxss1 \
    libasound2 \
    libatk-bridge2.0-0 \
    libgtk-3-0 \
    libgbm-dev \
    # Pulisce la cache apt per ridurre la dimensione finale dell'immagine
    && rm -rf /var/lib/apt/lists/* \

RUN set -eux; \
    # Definisci la variabile URL direttamente qui
    CHROME_VERSION_URL="https://googlechromelabs.github.io/chrome-for-testing/last-known-good-versions-with-downloads.json"; \
    \
    # Scarica le informazioni sulle versioni disponibili
    wget -qO /tmp/chrome_versions.json "$CHROME_VERSION_URL"; \
    \
    # Estrai l'ultima versione stabile di Chrome per Linux ARM64
    CHROME_URL=$(jq -r '.channels.Stable.downloads.chrome[] | select(.platform == "linux64") | .url' /tmp/chrome_versions.json); \
    if [ -z "$CHROME_URL" ]; then echo "Errore: URL di Chrome per linux64 non trovato."; exit 1; fi; \
    \
    # Estrai l'ultima versione stabile di ChromeDriver per Linux ARM64
    CHROMEDRIVER_URL=$(jq -r '.channels.Stable.downloads.chromedriver[] | select(.platform == "linux64") | .url' /tmp/chrome_versions.json); \
    if [ -z "$CHROMEDRIVER_URL" ]; then echo "Errore: URL di ChromeDriver per linux64 non trovato."; exit 1; fi; \
    \
    # Scarica Chromium
    echo "Scaricando Chromium da: $CHROME_URL"; \
    wget -qO /tmp/chrome.zip "$CHROME_URL"; \
    unzip /tmp/chrome.zip -d /opt/; \
    # Rinominare la cartella per renderla più gestibile, se necessario
    mv /opt/chrome-linux64 /opt/chrome; \
    # Crea un link simbolico per l'eseguibile di Chrome, utile per Puppeteer/Selenium
    ln -s /opt/chrome/chrome /usr/bin/google-chrome; \
    \
    # Scarica ChromeDriver
    echo "Scaricando ChromeDriver da: $CHROMEDRIVER_URL"; \
    wget -qO /tmp/chromedriver.zip "$CHROMEDRIVER_URL"; \
    unzip /tmp/chromedriver.zip -d /usr/local/bin/; \
    # ChromeDriver-linux64/chromedriver è la struttura tipica, quindi lo spostiamo e lo rendiamo eseguibile
    mv /usr/local/bin/chromedriver-linux64/chromedriver /usr/local/bin/; \
    chmod +x /usr/local/bin/chromedriver; \
    \
    # Pulizia
    rm /tmp/chrome_versions.json /tmp/chrome.zip /tmp/chromedriver.zip; \
    rm -rf /usr/local/bin/chromedriver-linux64;

# Set working directory
WORKDIR /home

# Make Python environment
RUN python3 -m venv /var/python/venv

#Install MediaFlowProxy
RUN /var/python/venv/bin/pip install mediaflow-proxy

#Install crawl4ai
RUN /var/python/venv/bin/pip install crawl4ai && \
    /var/python/venv/bin/pip install parsel && \
    /var/python/venv/bin/crawl4ai-setup

# Set working directory
WORKDIR $WORKDIR

# Copy the Laravel application and necessary files
COPY www $WORKDIR
COPY src /var/src

# Copy the PHP, Supervisor and Nginx configuration files
ADD src/php.ini $PHP_INI_DIR/conf.d/
ADD src/opcache.ini $PHP_INI_DIR/conf.d/
ADD src/supervisord.conf /etc/supervisor/supervisord.conf

COPY src/entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh &&  \
    ln -s /usr/local/bin/entrypoint.sh /

RUN rm -rf /etc/nginx/conf.d/default.conf && \
    rm -rf /etc/nginx/sites-enabled/default && \
    rm -rf /etc/nginx/sites-available/default && \
    rm -rf /etc/nginx/nginx.conf

COPY src/nginx.conf /etc/nginx/nginx.conf
COPY src/default.conf /etc/nginx/conf.d/

# Create the necessary folders
RUN mkdir -p /var/log/supervisor && \
    mkdir -p /var/log/nginx && \
    mkdir -p /var/cache/nginx && \
    mkdir -p /var/cache/nginx/temp && \
    mkdir -p /tmp/.X11-unix

# Create the www-data user
RUN usermod -u ${PUID} ${USER_NAME}
RUN groupmod -g ${PUID} ${GROUP_NAME}

# Change folder permissions
RUN chown -R ${USER_NAME}:${GROUP_NAME} /var/www && \
    chown -R ${USER_NAME}:${GROUP_NAME} /var/log/ && \
    chown -R ${USER_NAME}:${GROUP_NAME} /var/python/ && \
    chown -R ${USER_NAME}:${GROUP_NAME} /etc/supervisor/conf.d/ && \
    chown -R ${USER_NAME}:${GROUP_NAME} $PHP_INI_DIR/conf.d/ && \
    touch /var/run/nginx.pid && \
    chown -R ${USER_NAME}:${GROUP_NAME} /var/cache/nginx && \
    chown -R ${USER_NAME}:${GROUP_NAME} /var/lib/nginx/ && \
    chown -R ${USER_NAME}:${GROUP_NAME} /var/run/nginx.pid && \
    chown -R ${USER_NAME}:${GROUP_NAME} /var/log/supervisor && \
    chown -R ${USER_NAME}:${GROUP_NAME} /etc/nginx/nginx.conf && \
    chown -R ${USER_NAME}:${GROUP_NAME} /etc/nginx/conf.d/ && \
    chown -R ${USER_NAME}:${GROUP_NAME} /tmp && \
    chown -R root:root /tmp/.X11-unix && \
    chmod -R 1777 /tmp/.X11-unix


# End of process
#USER ${USER_NAME}
EXPOSE 8098
ENTRYPOINT ["entrypoint.sh"]