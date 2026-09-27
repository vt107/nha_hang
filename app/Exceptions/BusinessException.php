<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lỗi nghiệp vụ có thông điệp tiếng Việt hiển thị thẳng cho người dùng (hết món, bàn đã có khách...).
 */
class BusinessException extends RuntimeException {}
