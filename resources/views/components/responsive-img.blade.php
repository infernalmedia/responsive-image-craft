<div class="{{ $getContainerCssClass() }}">
    @if (!$skipPictureTag)
        <picture {{ $attributes->merge() }}>
            @if ($useResponsiveImages())
                @foreach ($getFilteredExtensions() as $extension)
                    <!-- load {{ $extension }} images if supported -->
                    <source type="{{ $getImageType($extension) }}"
                            srcset="{{ $getSrcset($extension) }}">
                @endforeach
            @endif
    @endif
    <img src="{{ $getUrlBasePath() . '/' . $src }}"
         {{ $imgAttributes }}
         @if ($skipPictureTag) {{ $attributes->merge() }} @endif
         @if ($useResponsiveImages()) srcset="{{ $getOriginalSrcset() }}" @endif
         @if (!empty($width) && !empty($height)) width="{{ $width }}"
         height="{{ $height }}" @endif
         alt="{{ $getAltAttribute() }}"
         decoding="{{ $getDecoding() }}"
         loading="{{ $getLoading() }}">


    @if (!$skipPictureTag)
        </picture>
    @endif
</div>
