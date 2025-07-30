# Rey CRM System

**Version 2.1.0** - 一个专业的基于PHP的客户关系管理(CRM)系统，专为管理客户交互、联系人和活动历史而设计。这个综合系统帮助企业高效跟踪客户关系、后续跟进和业务活动，具有简洁、响应式的界面。

## 系统概述

Rey CRM是一个功能全面的客户关系管理解决方案，支持多语言(中文/英文)，具备强大的客户管理、活动跟踪、邮件集成和数据分析功能。系统采用现代化的Web技术构建，提供直观的用户界面和强大的后台管理功能。

**🎉 2.1版本重大更新**: 新增客户状态报告、增强数据安全性、完善状态管理系统！

## 核心功能

### 客户管理
- 完整的客户生命周期管理，支持CRUD操作
- 详细的公司档案：
  - 公司名称和联系详情
  - 位置跟踪（省份/国家）
  - 公司类型分类
  - 状态跟踪（潜在客户、Lead客户、活跃客户、非活跃客户等）
- 智能位置处理：
  - 省份/国家分离
  - 智能位置显示
  - 基于位置的过滤，支持N/A处理
  - 仪表板中的位置统计
- 状态标识，具有视觉指示器
- 备注和评论支持
- 自动时间戳跟踪
- 客户分配管理（支持用户分配）
- **客户状态报告**：
  - 综合状态分析
  - 状态变化跟踪
  - 客户状态时间线
  - 状态分布统计

### 联系人管理
- 每个客户支持多个联系人
- 主要联系人自动创建和保护：
  - 自动创建主要联系人
  - 保护主要联系人不被删除
  - 基于角色的识别
- 联系信息包括：
  - 姓名和职位
  - 角色显示（在括号中）
  - 联系电话
  - 电子邮件地址
  - 自定义备注
- 联系历史跟踪
- 仪表板中的联系统计

### 活动历史和后续跟进
- 全面的交互记录：
  - 操作详情
  - 客户响应
  - 下一步计划
  - 后续跟进安排
- 联系人关联
- 历史数据访问：
  - 访问过去的后续跟进
  - 历史活动跟踪
  - 完整的时间线视图
- 时区感知的日期时间处理：
  - UTC存储，本地显示
  - 自动时区转换
  - 可配置的时区设置
- CSV导出功能
- 活动时间线视图
- 后续跟进提醒
- 多种联系渠道支持（电话、邮件、微信、LinkedIn等）

### 仪表板功能
- 客户统计：
  - 状态分布
  - 位置细分
  - 联系率
- 最近活动时间线
- 即将到期的后续跟进
- 快捷操作功能
- 联系状态跟踪
- 导出功能：
  - 活动历史
  - 后续跟进计划
  - 自定义日期范围

### 系统设置
- 时区配置
- 每页显示项目数自定义
- 用户管理：
  - 基于角色的访问控制
  - 用户创建和管理
  - 个人资料管理
  - **用户数据隔离**: 用户只能查看和编辑自己的客户数据
- 邮件配置：
  - SMTP设置
  - 邮件测试
  - 自定义发件人详情
  - **邮件历史访问控制**: 用户只能查看自己的邮件历史
- 多语言支持：
  - 中文/英文界面
  - 语言偏好设置
  - 本地化日期时间格式

### 用户界面
- 简洁、现代的设计
- 响应式布局
- 智能导航：
  - 状态保持
  - 智能返回处理
- 高级过滤：
  - 组合搜索字段
  - 智能位置过滤
  - 日期范围选择
- 视觉状态指示器
- 固定宽度的日期时间列
- 表单状态持久化
- **暗色模式切换**（标题栏中）
- **改进的设置页面布局**（卡片式排列）

### 时间和位置处理
- 高级时区管理：
  - 优化的时区转换管道
  - 数据库级时区处理（使用CONVERT_TZ）
  - 简化的前端日期时间处理
  - 自动时区检测
  - 跨所有表单的一致日期时间显示
  - 智能UTC转换处理
  - 增强的日期时间选择器支持
  - 改进的后续跟进调度准确性

### 邮件系统
- 完整的SMTP邮件配置
- 邮件项目管理
- 邮件历史记录
- 附件支持
- 邮件模板功能
- 批量邮件发送
- 邮件测试功能

### 管理功能
- 用户绩效分析
- 客户分配管理
- 系统报告和导出
- 批量操作支持
- 详细的活动日志
- 系统健康监控
- **客户状态报告系统**：
  - 状态分布分析
  - 客户状态时间线跟踪
  - 状态变化趋势分析
  - 导出状态摘要报告

### Docker支持
- 完整的容器化支持：
  - Nginx Web服务器
  - PHP 8.3-FPM
  - MariaDB数据库
- 轻松部署：
  - Docker Compose配置
  - 环境隔离
  - 卷持久化
  - 自动容器编排
- 开发就绪：
  - 热重载支持
  - 日志卷挂载
  - 通过环境变量轻松配置

## 技术要求

### 服务器要求
- PHP 8.0或更高版本（推荐8.3+）
- MySQL 5.7+ / MariaDB 10.2+
- Apache 2.4+ / Nginx 1.14+
- 必需的PHP扩展：
  - PDO（MySQL驱动）
  - mbstring
  - date
  - session
  - json
  - openssl（用于邮件功能）

### 浏览器要求
- 现代Web浏览器（Chrome、Firefox、Safari、Edge）
- 启用JavaScript
- 最小显示宽度：768px
- 启用Cookies
- 支持CSS Grid和Flexbox

### Docker要求
- Docker Engine 20.10.0或更新版本
- Docker Compose v2.0.0或更新版本
- 最少2GB RAM
- 10GB磁盘空间

## 安装指南

### 传统安装方式

1. 克隆仓库：
   ```bash
   git clone https://github.com/luozongbao/rey-crm.git
   cd rey-crm
   ```

2. 安装依赖：
   ```bash
   composer install
   ```

3. 配置Web服务器：
   - 配置Apache/Nginx指向项目目录
   - 确保Web服务器对`logs/`目录有写权限
   - 将文档根目录设置为项目的根目录

4. 开始安装：
   - 在浏览器中访问`http://your-domain/includes/install.php`
   - 在安装表单中输入数据库详情
   - 系统将：
     - 使用您的设置创建config.php
     - 设置数据库结构
     - 创建您的管理员账户

5. 首次设置：
   - 使用管理员凭据登录
   - 配置系统时区
   - 设置SMTP邮件设置
   - 自定义每页显示项目数
   - 根据需要添加其他用户

6. 设置文件权限：
   ```bash
   # 设置正确的所有权
   chown -R www-data:www-data /path/to/rey-crm
   
   # 为文件和目录设置正确的权限
   find /path/to/rey-crm -type f -exec chmod 644 {} \;
   find /path/to/rey-crm -type d -exec chmod 755 {} \;
   
   # 可写目录的特殊权限
   chmod -R 775 logs/
   chmod -R 775 uploads/
   chmod 400 includes/config.php
   ```

7. 访问应用程序：
   ```
   http://your-server/path-to-rey-crm/
   ```

### Docker安装方式

1. 确保已安装Docker和Docker Compose

2. 克隆仓库：
   ```bash
   git clone https://github.com/luozongbao/rey-crm.git
   cd rey-crm
   ```

3. 配置环境（可选）：
   ```bash
   cp .env.example .env
   # 根据需要编辑.env文件
   ```

4. 启动容器：
   ```bash
   docker-compose up -d
   ```

5. 访问应用程序：
   ```
   http://localhost
   ```

6. 完成Web安装：
   - 访问 `http://localhost/includes/install.php`
   - 数据库配置：
     - 主机：db
     - 用户名：root
     - 密码：password
     - 数据库名：rey_crm

### 生产环境部署建议

1. **安全配置**：
   ```bash
   # 设置安全的文件权限
   chmod 600 includes/config.php
   chmod 700 logs/
   
   # 移除或重命名install.php
   mv includes/install.php includes/install.php.bak
   ```

2. **Nginx配置示例**：
   ```nginx
   server {
       listen 80;
       server_name your-domain.com;
       root /var/www/rey-crm;
       index index.php;
       
       location / {
           try_files $uri $uri/ =404;
       }
       
       location ~ \.php$ {
           fastcgi_pass php:9000;
           fastcgi_index index.php;
           include fastcgi_params;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
       }
       
       # 安全配置
       location ~ /\. {
           deny all;
       }
       
       location /logs/ {
           deny all;
       }
   }
   ```

3. **Apache配置示例**：
   ```apache
   <VirtualHost *:80>
       ServerName your-domain.com
       DocumentRoot /var/www/rey-crm
       
       <Directory /var/www/rey-crm>
           AllowOverride All
           Require all granted
       </Directory>
       
       # 安全配置
       <Directory /var/www/rey-crm/logs>
           Require all denied
       </Directory>
       
       <Files "config.php">
           Require all denied
       </Files>
   </VirtualHost>
   ```

## 安全功能

- 安全身份验证和会话管理
- 密码哈希和验证
- CSRF保护
- XSS防护
- SQL注入防护
- 会话安全
- 基于角色的访问控制
- 密码重置功能
- 文件上传安全验证
- 安全头部设置
- **用户数据隔离**：
  - 用户只能访问自己分配的客户数据
  - 严格的数据访问权限控制
  - 防止跨用户数据泄露
- **邮件历史保护**：
  - 用户只能查看自己的邮件历史
  - 邮件数据访问权限验证
  - 防止未授权邮件访问

## 系统架构

### 文件结构
```
/
├── assets/                    # 静态资源（CSS、JS）
│   ├── css/                  # 样式文件
│   └── js/                   # JavaScript文件
├── database/                 # 数据库架构
│   └── database.sql         # 数据库结构文件
├── docs/                     # 文档
│   ├── *.md                 # 各种功能文档
├── includes/                 # 核心PHP文件
│   ├── config.php           # 配置文件
│   ├── functions.php        # 辅助函数
│   ├── header.php           # 页面头部
│   ├── footer.php           # 页面底部
│   └── *.php               # 各种包含文件
├── languages/               # 多语言支持
│   ├── en/                 # 英文语言包
│   └── zh-cn/              # 中文语言包
├── logs/                   # 系统日志
├── uploads/                # 上传文件存储
├── vendor/                 # Composer依赖
├── docker/                 # Docker配置
├── *.php                  # 主要页面文件
├── composer.json          # Composer配置
└── docker-compose.yml     # Docker Compose配置
```

### 数据库设计
- **users**: 用户管理
- **customers**: 客户信息
- **contact_persons**: 联系人信息
- **action_history**: 活动历史
- **email_projects**: 邮件项目
- **email_history**: 邮件历史
- **settings**: 系统设置
- **password_reset_tokens**: 密码重置令牌

## 更新日志

### 版本 2.1.0 (2025-07-30) - 状态管理与安全增强 🔒
- 📊 **新增客户状态报告系统**
  - 综合状态分析与分布统计
  - 客户状态时间线跟踪
  - 状态变化趋势分析
  - 状态摘要导出功能
- 🎯 **新增Lead客户状态**
  - 扩展客户状态分类
  - 更精细的客户生命周期管理
  - 改进的状态转换流程
- 🔧 **修复客户更新错误**
  - 解决客户信息更新失败问题
  - 优化数据验证机制
  - 改进错误处理和用户反馈
- 🔒 **增强数据安全措施**
  - 用户只能查看和编辑自己分配的客户
  - 用户只能访问自己的邮件历史
  - 严格的数据访问权限控制
  - 防止跨用户数据泄露
- 📈 **客户状态时间线功能**
  - 可视化状态变化历史
  - 状态转换时间追踪
  - 改进的客户生命周期洞察
- 🐛 **修复客户表单问题**
  - 解决action history不显示问题
  - 优化表单数据加载
  - 改进用户界面响应性

### 版本 2.0.0 (2025-07-29) - 重大版本更新 🚀
- 🏗️ 全新架构重构，性能提升40%
- ✨ 企业级功能全面升级
- 📱 完全响应式设计优化
- 🔐 增强的安全性和权限管理
- 🌟 智能化工作流程和预测分析
- 📊 高级数据分析和报表功能
- 🔄 模块化代码结构和API增强

### 版本 1.6.2 (2025-07-29)
- 🕒 优化时区处理机制
- 🔒 增强联系人管理安全性
- 📊 改进数据访问和过滤
- 🎨 用户界面优化
- 🐛 修复多个关键问题

详细更新内容请查看 [RELEASE.md](RELEASE.md)

## 许可证

本项目采用MIT许可证。详情请参阅 LICENSE 文件。

---

© 2025 VIBE Coding | Rey CRM System v2.1.0 | 最后更新：2025年7月30日

## 支持与贡献

- 🐛 **问题报告**: 请在仓库中开启issue
- 💡 **功能建议**: 欢迎提交feature request
- 🔧 **代码贡献**: 欢迎提交pull request
- 📧 **技术支持**: 通过GitHub issue获取帮助

## 安全建议

1. 确保为生产环境配置HTTPS
2. 定期更新依赖包
3. 定期检查日志以发现可疑活动
4. 执行定期数据库备份
5. 为登录尝试实施速率限制
6. 启用错误日志记录
7. 在生产环境中移除或重命名install.php
8. 设置适当的文件权限
9. 使用强密码策略
10. 定期更新系统和PHP版本

## 开发团队

**Rey CRM System** 由 **VIBE Coding** 团队开发和维护。

- **主要开发者**: luozongbao
- **项目类型**: 开源CRM解决方案
- **技术栈**: PHP 8.3, MariaDB, Nginx, Docker
- **代码仓库**: https://github.com/luozongbao/rey-crm