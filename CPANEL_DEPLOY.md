# cPanel 部署说明

## 数据库

在 cPanel → **MySQL Databases** 创建数据库与用户，并写入 `includes/config.local.php`  
（可从 `includes/config.local.example.php` 复制）。

常见项：

| 项 | 说明 |
|----|------|
| Host | 通常是 `localhost` |
| Database / User / Password | 使用你在 cPanel 创建的值 |

## 上传

1. 解压到 `public_html/ai_shop_assistant/`（或你的子目录）
2. 确认可写：`uploads/products/`、`uploads/cache/`

## 路径说明

`APP_BASE_PATH` 留空即可：本机与 cPanel 都会自动侦测子目录路径。  
若 CSS 仍错，再在 `config.local.php` 手动设：`putenv('APP_BASE_PATH=/ai_shop_assistant');`

## 导入数据库

1. cPanel → **phpMyAdmin**
2. 选中你的数据库
3. **Import** → 选择 `sql/cpanel_setup.sql` → Go

导入后会创建：
- 管理员账号
- AI 设置占位（请在后台填入 API key）
- 6 个示例商品

## 锁定安装向导

导入 SQL 成功后，在服务器上创建空文件：

```text
includes/install.lock
```

（或访问一次 `install.php`，若已侦测到数据库会自动建立 lock）

## 管理员登录

- Email: `admin@shop.local`
- Password: `Admin@12345`

登录后请立刻改密码，并在 **AI Settings** 填入 API key。

## HTTPS / 手机扫码

使用正式域名 HTTPS，例如：

```text
https://你的域名.com/ai_shop_assistant/
```

首页 QR 会自动用当前域名。

## AI Connection 失败排查

1. **确认已上传** `includes/config.local.php` 和 `includes/certs/cacert.pem`
2. cPanel → **Select PHP Version** → 启用扩展：`curl`、`openssl`
3. AI Settings 里选好 Provider / Model，保存后再 Test
4. 若提示 SSL：在 `config.local.php` 打开 `putenv('AI_SSL_INSECURE=1');`
5. 若超时：问主机商是否封锁出站访问 `apihub.agnes-ai.com` / `generativelanguage.googleapis.com`

重新测：Admin → AI Settings → **Test AI Connection**
