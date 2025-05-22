FROM nginx:latest

RUN apt-get update && \
    apt-get install -y curl

# install composer
RUN apt install -y php-cli php-zip php-mbstring php-curl php-xml php-json unzip
RUN curl -sS https://getcomposer.org/installer -o composer-setup.php
RUN php composer-setup.php --install-dir=/usr/local/bin --filename=composer
RUN rm composer-setup.php
RUN composer --version
RUN apt-get clean

COPY . /var/www/html
WORKDIR /var/www/html
RUN composer install
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]