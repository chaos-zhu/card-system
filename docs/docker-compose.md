# Docker Compose 部署

此部署方案只提供 HTTP 服务。HTTPS 应由现有的外部反向代理终止，再将请求转发到 `http://<宿主机>:<HTTP_PORT>`。

## 首次部署

1. 复制环境变量模板：

   ```sh
   cp .env.docker.example .env
   ```

   修改 `.env` 中的数据库密码、公开的 `https://` 站点地址，并将反向代理的 IP 或 CIDR 写入 `TRUSTED_PROXIES`。不要继续使用模板中的示例密码。

2. 拉取已发布的应用镜像：

   ```sh
   docker compose pull
   ```

3. 使用应用镜像生成随机 `APP_KEY`。该命令不启动 Laravel，也不依赖数据库：

   ```sh
   docker compose run --rm --no-deps app php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
   ```

   将命令输出的完整内容复制到宿主机 `.env` 的 `APP_KEY=` 后面，例如：

   ```dotenv
   APP_KEY=base64:生成的随机内容
   ```

   `APP_KEY` 用于加密应用数据，部署后不得随意更换，也不要提交到 Git 仓库。

4. 启动数据库和 Redis，等待健康检查通过：

   ```sh
   docker compose up -d db redis
   docker compose ps
   ```

5. 初始化新数据库，然后启动全部服务：

   ```sh
   docker compose run --rm app php artisan migrate --seed --force
   docker compose up -d
   ```

6. 登录后台，在系统设置中配置与 `.env` 一致的公开 HTTPS 域名。支付同步返回地址和异步通知地址必须使用该公网域名。

## 外部反向代理

反向代理需要传递以下请求头：

- `Host`
- `X-Forwarded-For`
- `X-Forwarded-Host`
- `X-Forwarded-Proto: https`

应通过防火墙尽量只允许反向代理访问宿主机公开的 HTTP 端口。`TRUSTED_PROXIES` 只能填写真实的代理地址；当 HTTP 端口对公网开放时，不要使用 `*`。

## 日常运维

查看日志：

```sh
docker compose logs -f web app queue scheduler
```

执行数据库迁移：

```sh
docker compose run --rm app php artisan migrate --force
```

拉取并部署新镜像：

```sh
docker compose pull
docker compose up -d
```

`IMAGE_TAG=latest` 表示部署当前 `master` 分支的最新镜像。若要部署某次指定提交，请先在 `.env` 中设置：

```dotenv
IMAGE_TAG=sha-完整提交SHA
```

然后执行 `docker compose pull` 和 `docker compose up -d`。

MySQL、Redis、Laravel 上传文件和日志使用命名卷持久化。升级前应备份 `db_data` 和 `app_storage`。生产环境不要执行 `docker compose down -v`，该命令会删除持久化数据。

## API 发货数据库

配置为 API 发货的商品使用可选的 `API_CARD_DB_*` 环境变量。不使用此功能时可将这些变量留空。使用时，必须确保 `app` 和 `queue` 容器能够访问所配置的外部数据库。
