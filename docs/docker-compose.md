# Docker Compose 部署

应用容器自身提供 HTTP 服务，外部反向代理不是启动应用的必需组件。内网或测试环境可以通过服务器 IP 和 `HTTP_PORT` 直接访问；公网生产环境强烈建议使用外部反向代理提供 HTTPS。

## 首次部署

1. 创建部署目录，并从 GitHub 下载 `docker-compose.yaml` 和环境变量模板：

   ```sh
   mkdir -p card-system
   cd card-system
   curl -fsSL https://raw.githubusercontent.com/chaos-zhu/card-system/master/docker-compose.yaml -o docker-compose.yaml
   curl -fsSL https://raw.githubusercontent.com/chaos-zhu/card-system/master/.env.docker.example -o .env
   ```

   后续命令均在此 `card-system` 目录中执行。修改 `.env` 中的数据库密码、公开的 `https://` 站点地址，并将反向代理的 IP 或 CIDR 写入 `TRUSTED_PROXIES`。不要继续使用模板中的示例密码。

2. 创建本地持久化目录，并确保 PHP 容器能够写入应用存储目录：

   ```sh
   mkdir -p data/storage/app/public data/storage/framework/cache data/storage/framework/sessions data/storage/framework/views data/storage/logs data/mysql data/redis
   sudo chown -R 33:33 data/storage
   ```

   MySQL、Redis 和 Laravel 文件均保存在当前目录的 `data/` 下，便于直接迁移和备份。Linux 中 PHP 容器使用 UID/GID `33:33`；如果部署环境使用其他权限映射，请相应调整 `data/storage` 的所有者。

3. 拉取已发布的应用镜像：

   ```sh
   docker compose pull
   ```

4. 使用应用镜像生成随机 `APP_KEY`。该命令不启动 Laravel，也不依赖数据库：

   ```sh
   docker compose run --rm --no-deps app php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
   ```

   将命令输出的完整内容复制到宿主机 `.env` 的 `APP_KEY=` 后面，例如：

   ```dotenv
   APP_KEY=base64:生成的随机内容
   ```

   `APP_KEY` 用于加密应用数据，部署后不得随意更换，也不要提交到 Git 仓库。

5. 启动数据库和 Redis，等待健康检查通过：

   ```sh
   docker compose up -d db redis
   docker compose ps
   ```

6. 初始化新数据库，然后启动全部服务：

   ```sh
   docker compose run --rm app php artisan migrate --seed --force
   docker compose up -d
   ```

7. 按下文的访问方式打开后台，立即修改默认密码，并在系统设置中配置正确的站点地址。

## 部署完成后访问

默认前台和后台地址如下：

- 前台商城：`http://服务器IP:8080/`
- 管理后台：`http://服务器IP:8080/admin`
- 默认管理员账号：`admin@qq.com`
- 默认管理员密码：`123456`

首次登录后必须立即修改默认密码。

如果修改了 `.env` 中的 `HTTP_PORT`，请将地址中的 `8080` 替换为实际端口，并在服务器防火墙中允许所需来源访问该端口。

### 不使用反向代理

不使用反向代理时，将 `.env` 配置为实际的 HTTP 地址：

```dotenv
APP_URL=http://服务器IP:8080
APP_URL_API=http://服务器IP:8080
HTTP_PORT=8080
TRUSTED_PROXIES=
```

`TRUSTED_PROXIES` 为空时，应用不会信任任何 `X-Forwarded-*` 请求头，适合没有反向代理、直接访问应用端口的情况。然后在管理后台的系统设置中，将站点 URL 和 API URL 同样改为该 HTTP 地址。此方式不会加密登录信息、支付数据和卡密内容，只适合可信内网或临时测试，不建议直接用于公网生产环境。

### 使用外部 HTTPS 反向代理

使用反向代理时，将 `.env` 中的 `APP_URL` 和 `APP_URL_API` 设置为公网 HTTPS 域名，例如 `https://cards.example.com`，并把真实代理 IP 或 CIDR 写入 `TRUSTED_PROXIES`。代理后端指向：

```text
http://服务器IP:8080
```

部署完成后通过以下地址访问：

- 前台商城：`https://cards.example.com/`
- 管理后台：`https://cards.example.com/admin`

管理后台的站点 URL、API URL、支付同步返回地址和异步通知地址也必须使用相同的 HTTPS 公网域名。

## 外部反向代理配置

反向代理需要传递以下请求头：

- `Host`
- `X-Forwarded-For`
- `X-Forwarded-Host`
- `X-Forwarded-Proto: https`

应通过防火墙尽量只允许反向代理访问宿主机公开的 HTTP 端口。`TRUSTED_PROXIES` 只能填写真实的代理地址；多个代理 IP 或 CIDR 使用英文逗号分隔，例如：

```dotenv
TRUSTED_PROXIES=10.0.0.2,10.0.0.3
```

使用反向代理但将此变量留空时，应用会忽略代理发送的 `X-Forwarded-For`、`X-Forwarded-Host` 和 `X-Forwarded-Proto`，可能把代理 IP 识别为客户端 IP，并无法正确判断原始请求使用 HTTPS。当 HTTP 端口对公网开放时，不要设置为 `*`，否则客户端可能伪造来源 IP 和协议头。

## 日常运维

查看日志：

```sh
docker compose logs -f web app queue scheduler
```

执行数据库迁移：

```sh
docker compose run --rm app php artisan migrate --force
```

## 后续升级

生产环境建议使用 `sha-完整提交SHA`，不要依赖可能随时变化的 `latest` 标签。升级前先记录当前 `IMAGE_TAG`，用于必要时回滚。

1. 创建一致性备份：

   ```sh
   mkdir -p backups
   docker exec card-system-db sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > backups/database-before-upgrade.sql
   tar -czf backups/storage-before-upgrade.tar.gz data/storage
   ```

2. 下载最新 Compose 文件进行对比，不要覆盖现有 `.env`：

   ```sh
   curl -fsSL https://raw.githubusercontent.com/chaos-zhu/card-system/master/docker-compose.yaml -o docker-compose.yaml.new
   diff -u docker-compose.yaml docker-compose.yaml.new || true
   mv docker-compose.yaml.new docker-compose.yaml
   curl -fsSL https://raw.githubusercontent.com/chaos-zhu/card-system/master/.env.docker.example -o .env.docker.example
   ```

   对照新的 `.env.docker.example`，将新增变量手动补充到现有 `.env`，不要直接覆盖包含密钥和密码的 `.env`。

3. 在 `.env` 中设置要部署的镜像版本：

   ```dotenv
   IMAGE_TAG=sha-完整提交SHA
   ```

4. 进入维护模式，停止后台任务，拉取并部署新镜像：

   ```sh
   docker compose exec app php artisan down
   docker compose stop queue scheduler
   docker compose pull
   docker compose up -d --no-deps app web
   ```

5. 执行数据库迁移、清理缓存并恢复服务：

   ```sh
   docker compose run --rm app php artisan migrate --force
   docker compose exec app php artisan cache:clear
   docker compose up -d
   docker compose exec app php artisan up
   docker compose ps
   ```

6. 检查前台、后台、队列和日志：

   ```sh
   docker compose logs --tail=100 app web queue scheduler
   ```

若升级失败，将 `.env` 中的 `IMAGE_TAG` 恢复为升级前记录的 SHA 标签，然后重新拉取并启动旧镜像：

```sh
docker compose pull
docker compose up -d --no-deps app web
docker compose up -d
```

数据库迁移不一定支持直接降级。如果新版本已经执行了不可逆迁移，应先停止服务，再从升级前的数据库和 `data/storage` 备份恢复。

MySQL、Redis、Laravel 上传文件和日志分别保存在 `./data/mysql`、`./data/redis` 和 `./data/storage`。迁移或升级前，可停止服务后直接备份整个 `data/` 目录；恢复时保持原目录结构和文件权限。不要在数据库仍在写入时直接复制 MySQL 数据目录，生产环境应优先使用 `mysqldump` 创建一致性备份。

## API 发货数据库

配置为 API 发货的商品使用可选的 `API_CARD_DB_*` 环境变量。不使用此功能时可将这些变量留空。使用时，必须确保 `app` 和 `queue` 容器能够访问所配置的外部数据库。
