FROM php:8.2-apache
COPY . /var/www/html/
RUN docker-php-ext-install mysqli pdo pdo_mysql && a2enmod rewrite
# Compat: o codigo usa caminhos /avaliacao web/... cria atalho para a raiz
RUN ln -s /var/www/html "/var/www/html/avaliacao web"
EXPOSE 80
