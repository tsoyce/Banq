{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
{{-- A37 · RSS-лента журнала (30 свежих публикаций текущей страны + общие). --}}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
    <title>{{ settings('site.name') }} — журнал</title>
    <link>{{ route('blog.index') }}</link>
    <description>{{ settings('site.description') }}</description>
    <language>ru</language>
    <atom:link href="{{ route('blog.rss') }}" rel="self" type="application/rss+xml"/>
    @if ($posts->first()?->published_at)<lastBuildDate>{{ $posts->first()->published_at->toRfc2822String() }}</lastBuildDate>@endif
@foreach ($posts as $p)
    <item>
        <title>{{ $p->title }}</title>
        <link>{{ $p->url() }}</link>
        <guid isPermaLink="true">{{ $p->url() }}</guid>
        <pubDate>{{ $p->published_at->toRfc2822String() }}</pubDate>
        @if ($p->category)<category>{{ $p->category->name }}</category>@endif
        @if ($p->author)<dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">{{ $p->author->name }}</dc:creator>@endif
        <description>{{ $p->metaDescription() }}</description>
        @if ($p->coverUrl())<enclosure url="{{ url($p->coverUrl()) }}" type="image/{{ str_ends_with($p->cover, '.png') ? 'png' : (str_ends_with($p->cover, '.webp') ? 'webp' : 'jpeg') }}" length="0"/>@endif
    </item>
@endforeach
</channel>
</rss>
