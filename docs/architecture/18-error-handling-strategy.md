# 18. Error Handling Strategy

## 18.1 Backend Error Handling

```php
// app/Exceptions/Handler.php
class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        if ($request->is('api/*')) {
            if ($exception instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Resource not found'], 404);
            }

            if ($exception instanceof ValidationException) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $exception->errors(),
                ], 422);
            }

            return response()->json([
                'message' => 'Server error',
                'error' => $exception->getMessage(),
            ], 500);
        }

        return parent::render($request, $exception);
    }
}
```

---
