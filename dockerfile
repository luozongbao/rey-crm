	# Stage 1: Build PHP-FPM image
FROM php:8.3-fpm AS php
	# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && rm -rf /var/lib/apt/lists/*
	# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    zip \
    gd \
    mbstring \
    exif \
    pcntl \
    bcmath
	# Create and switch to a non-root user
RUN useradd -r -u 1000 -g www-data appuser
USER appuser
	# Set working directory
WORKDIR /var/www/html
	# Copy application files (excluding what's in .dockerignore)
COPY --chown=appuser:www-data . .
	# Stage 2: Build Nginx image
FROM nginx:alpine AS webserver
	# Copy custom nginx configuration
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
	# Copy built assets from PHP stage
COPY --from=php /var/www/html /var/www/html
	# Expose port 80
EXPOSE 80
	# Start Nginx
CMD ["nginx", "-g", "daemon off;"]
