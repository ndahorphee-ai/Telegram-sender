FROM php:8.2-cli-alpine

WORKDIR /app

# copier les fichiers
COPY . .

# exposer le port 8080
EXPOSE 8080

# lancer ton serveur PHP intégré
CMD ["php", "-S", "127.0.0.1:8080", "-t", "public/router.php"]

