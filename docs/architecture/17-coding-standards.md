# 17. Coding Standards

## 17.1 PHP (PSR-12)

```php
<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Create a new order
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create([
                'user_id' => $data['user_id'],
                'total_amount' => $data['total_amount'],
            ]);

            return $order;
        });
    }
}
```

---

## 17.2 TypeScript/JavaScript (ESLint)

```typescript
// lib/services/course-service.ts
import { apiClient } from '@/lib/api'
import type { Course, CreateCourseDto } from '@/lib/types'

export class CourseService {
  static async getCourses(environmentId: number): Promise<Course[]> {
    const response = await apiClient.get('/courses', {
      params: { environment_id: environmentId },
    })
    return response.data.data
  }
}
```

---
