FROM nginx:latest


RUN rm /etc/nginx/conf.d/default.conf

COPY ./conf.d/ /etc/nginx/conf.d/

# Создаем директорию для логов
RUN mkdir -p /var/log/nginx && \
    touch /var/log/nginx/track-api-access.log \
          /var/log/nginx/track-api-error.log \
          /var/log/nginx/track-access.log \
          /var/log/nginx/track-error.log

# Открываем порты (80 для PHP/Сайта, 8000 для FastAPI)
EXPOSE 80 8000

# Запускаем Nginx
CMD ["nginx", "-g", "daemon off;"]