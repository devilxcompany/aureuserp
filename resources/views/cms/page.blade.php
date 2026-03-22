<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title }}</title>
    @if ($page->meta_description)
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
    @if ($page->meta_keywords)
        <meta name="keywords" content="{{ $page->meta_keywords }}">
    @endif
</head>
<body>
    <main>
        <h1>{{ $page->title }}</h1>

        @if ($page->content)
            <div class="page-content">
                {!! $page->content !!}
            </div>
        @endif

        @foreach ($page->contentBlocks as $block)
            <section class="content-block content-block--{{ $block->type }}">
                @if (!empty($block->name))
                    <h2>{{ $block->name }}</h2>
                @endif
                @if (!empty($block->content['body']))
                    <div>{!! $block->content['body'] !!}</div>
                @endif
            </section>
        @endforeach
    </main>
</body>
</html>
