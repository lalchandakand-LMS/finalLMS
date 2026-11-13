<section class="card" data-page-section style="margin-bottom:14px;">
    <div class="page-header"><h3>Section <span data-section-number>{{ is_numeric($i) ? $i + 1 : '' }}</span></h3><div class="cta-row"><button class="btn secondary" type="button" data-move-section="up">↑</button><button class="btn secondary" type="button" data-move-section="down">↓</button><button class="btn danger" type="button" data-remove-page-section>Remove</button></div></div>
    <div class="grid grid-2">
        <label>Block type<select name="sections[{{ $i }}][type]">@foreach(['hero'=>'Hero banner','text'=>'Text content','image'=>'Image section','feature'=>'Feature / highlights','cta'=>'Call to action'] as $value=>$label)<option value="{{ $value }}" @selected(($section['type'] ?? 'text')===$value)>{{ $label }}</option>@endforeach</select></label>
        <label>Heading<input name="sections[{{ $i }}][title]" maxlength="180" value="{{ $section['title'] ?? '' }}"></label>
    </div>
    <label>Text / description<textarea name="sections[{{ $i }}][body]" rows="3" maxlength="5000">{{ $section['body'] ?? '' }}</textarea></label>
    <div class="grid grid-2">
        <label>Image<select name="sections[{{ $i }}][image_id]" data-section-image><option value="">No image</option>@foreach($media as $asset)<option value="{{ $asset->id }}" @selected((string)($section['image_id'] ?? '')===(string)$asset->id)>{{ $asset->original_name }}</option>@endforeach</select><small class="muted" data-image-preview>{{ !empty($section['image_id']) ? 'Selected media #'.$section['image_id'] : 'No image selected' }}</small></label>
        <label>Layout<select name="sections[{{ $i }}][layout]"><option value="standard" @selected(($section['layout'] ?? 'standard')==='standard')>Text then image</option><option value="reversed" @selected(($section['layout'] ?? '')==='reversed')>Image then text</option><option value="centered" @selected(($section['layout'] ?? '')==='centered')>Centered</option></select></label>
        <label>Button label<input name="sections[{{ $i }}][button_label]" maxlength="60" value="{{ $section['button_label'] ?? '' }}"></label>
        <label>Button URL<input name="sections[{{ $i }}][button_url]" maxlength="500" value="{{ $section['button_url'] ?? '' }}" placeholder="/courses"></label>
        <label>Background color<input type="color" name="sections[{{ $i }}][background_color]" value="{{ $section['background_color'] ?? '#ffffff' }}"></label>
        <label>Text color<input type="color" name="sections[{{ $i }}][text_color]" value="{{ $section['text_color'] ?? '#18212f' }}"></label>
    </div>
    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="sections[{{ $i }}][enabled]" value="1" style="width:auto;" @checked($section['enabled'] ?? true)> Section enabled</label>
</section>
