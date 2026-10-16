<div class="{{ $getContainerCssClass() }}">
    @if (!$skipPictureTag)
        <picture>
            @if ($enabled())
                @foreach ($getFilteredExtensions() as $extension)
                    @php($type = $getImageType($extension))
                    @continue($type === '')
                    <!-- load {{ $extension }} images if supported -->
                    <source type="{{ $type }}"
                            srcset="{{ $getSrcset($extension) }}"
                            @if (!empty($sizes)) sizes="{{ $sizes }}" @endif>
                @endforeach
            @endif
    @endif
    <img src="{{ $getUrlBasePath() }}/{{ $src }}"
         @if (!empty($imgAttributes)) {!! $imgAttributes !!} @endif
         {{ $attributes->merge() }}
         @if ($enabled()) srcset="{{ $getOriginalSrcset() }}" @endif
         @if (!empty($sizes)) sizes="{{ $sizes }}" @endif
         @if (!empty($width) && !empty($height)) width="{{ $width }}"
         height="{{ $height }}" @endif
         @if (!empty($getFetchPriority())) fetchpriority="{{ $getFetchPriority() }}" @endif
         alt="{{ $getAltAttribute() }}"
         decoding="{{ $getDecoding() }}"
         loading="{{ $getLoading() }}">

    @if (!$skipPictureTag)
        </picture>
    @endif
</div>
