# iPhone Social Share Buttons for WordPress

Легкий WordPress-плагин с кнопками VK, Telegram, MAX, Одноклассники и WhatsApp в стиле iPhone.

![Скриншот кнопок](assets/screenshot.png)

## Возможности

- 5 кнопок для распространения ссылок.
- В админке можно включать и выключать любую кнопку.
- Счетчики показывают уникальные нажатия.
- Иконки хранятся локально, без CDN и сторонних библиотек.
- Легкий код без внешних сервисов и API.
- Поддержка PHP-шаблона и shortcode.

## Установка

1. Скопируйте папку `ios-social-share` в:

```
wp-content/plugins/
```

2. Активируйте **iPhone Social Share Buttons** в WordPress.
3. Настройте кнопки в **Настройки → iPhone Social Share**.

Вывод в PHP-шаблоне:

```php
<?php ios_social_share(); ?>
```

Или через shortcode:

```
[ios_social_share]
```

## Лицензия

GPL-2.0-or-later.

---

## Автор

Evgeny Fed

GitHub: https://github.com/jekfev
