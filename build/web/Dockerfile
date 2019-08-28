FROM node:8-alpine

# https://vuejs.org/v2/cookbook/dockerize-vuejs-app.html

# build stage
#WORKDIR /app-web
#COPY package*.json ./
#RUN npm install
#COPY . .
#RUN npm run build

# production stage /etc/nginx/html/
#COPY /app-web/dist /usr/share/nginx/html
#EXPOSE 80
#CMD ["nginx", "-g", "daemon off;"]

COPY /app-web/dist /app-web
