<?php declare(strict_types=1);

namespace Lantern\Schema\Types;

use Illuminate\Http\Request;
use Lantern\Execution\ResolveInfo;
use Lantern\Subscriptions\Subscriber;
use Lantern\Support\Contracts\GraphQLContext;

class NotFoundSubscription extends GraphQLSubscription
{
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        return false;
    }

    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        return false;
    }

    public function resolve(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): mixed
    {
        return null;
    }
}
