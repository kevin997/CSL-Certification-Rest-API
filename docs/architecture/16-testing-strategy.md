# 16. Testing Strategy

## 16.1 Test Pyramid

```
         /\
        /  \  E2E Tests (Cypress)
       /____\  10%
      /      \
     /        \ Integration Tests
    /__________\ 30%
   /            \
  /              \ Unit Tests
 /________________\ 60%
```

---

## 16.2 Backend Testing (Laravel)

**Unit Tests:**
```php
// tests/Unit/Services/CommissionServiceTest.php
class CommissionServiceTest extends TestCase
{
    public function test_calculates_commission_correctly()
    {
        $service = new CommissionService();
        $order = Order::factory()->make(['total_amount' => 100]);

        $commission = $service->calculateCommission($order);

        $this->assertEquals(17.00, $commission); // 17% of 100
    }
}
```

**Feature Tests:**
```php
// tests/Feature/Api/CourseControllerTest.php
class CourseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_course()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/courses', [
                'title' => 'New Course',
                'description' => 'Course description',
                'price' => 99.99,
            ]);

        $response->assertStatus(201)
            ->assertJson(['data' => ['title' => 'New Course']]);

        $this->assertDatabaseHas('courses', ['title' => 'New Course']);
    }
}
```

---

## 16.3 Frontend Testing

**Component Tests (Jest + React Testing Library):**
```typescript
// components/courses/CourseCard.test.tsx
import { render, screen } from '@testing-library/react'
import { CourseCard } from './CourseCard'

test('renders course card with title', () => {
  const course = { id: 1, title: 'Test Course', published: true }
  render(<CourseCard course={course} />)

  expect(screen.getByText('Test Course')).toBeInTheDocument()
  expect(screen.getByText('Published')).toBeInTheDocument()
})
```

---
