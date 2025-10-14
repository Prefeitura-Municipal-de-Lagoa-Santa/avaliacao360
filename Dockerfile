# Usa uma imagem base LTS do Ubuntu
FROM ubuntu:22.04

# Evita perguntas interativas durante a instalação de pacotes
ENV DEBIAN_FRONTEND=noninteractive

# Instala pré-requisitos, Nginx, Supervisor, PHP 8.3 e AGORA TAMBÉM O NODE.JS
RUN apt-get update && apt-get install -y \
    software-properties-common curl git unzip zip ca-certificates \
    nginx supervisor \
    # Adiciona o repositório do PHP
    && add-apt-repository ppa:ondrej/php -y \
    # --- INÍCIO DA ADIÇÃO: INSTALAR NODE.JS ---
    # Adiciona o repositório do Node.js (usaremos a versão 20.x, que é a LTS atual)
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    # --- FIM DA ADIÇÃO ---
    && apt-get update \
    && apt-get install -y \
    # Instala o Node.js e o npm
    nodejs \
    # Instala o PHP e suas extensões
    php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-ldap \
    php8.3-xml php8.3-gd php8.3-curl php8.3-zip php8.3-bcmath \
    # Limpa o cache para manter a imagem menor
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instala o Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Define o diretório de trabalho
WORKDIR /var/www/html

# Copia o código da sua aplicação Laravel
COPY . .

# Copia os arquivos de configuração
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/app.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Remove a configuração padrão do Nginx e ativa a nossa
RUN rm -f /etc/nginx/sites-enabled/default && \
    ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default && \
    chmod +x /usr/local/bin/entrypoint.sh

# Configura limites de upload no PHP para corresponder ao Nginx
RUN echo "upload_max_filesize=100M" >> /etc/php/8.3/fpm/php.ini \
    && echo "post_max_size=100M" >> /etc/php/8.3/fpm/php.ini \
    && echo "max_file_uploads=50" >> /etc/php/8.3/fpm/php.ini \
    && echo "file_uploads=On" >> /etc/php/8.3/fpm/php.ini \
    && echo "memory_limit=256M" >> /etc/php/8.3/fpm/php.ini

# Diretório de trabalho já definido; permissões serão ajustadas no entrypoint se necessário

# Expõe a porta 80, que o Nginx usará
EXPOSE 80

# Define o entrypoint que iniciará tudo
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]