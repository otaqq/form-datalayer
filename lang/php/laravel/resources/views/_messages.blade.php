@if (session('form-datalayer.saved'))
    <p class="saved" data-test="saved">Saved.</p>
@endif
@if (session('form-datalayer.conflict'))
    <p class="error" data-test="conflict">Someone else saved these settings first. Their settings are shown below with what you typed; check and save again.</p>
@endif
@if ($errors->has('form-datalayer'))
    <ul class="error" data-test="errors">
        @foreach ($errors->get('form-datalayer') as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
@if ($broken)
    <p class="error" data-test="broken">The saved settings cannot be read, so nothing is measured: {{ $broken }}. Saving replaces them.</p>
@endif
@if ($removed !== [])
    <div class="removed" data-test="removed">
        <p>No longer in the catalog or the routes (dropped on the next save):</p>
        <ul>
            @foreach ($removed as $name)
                <li>{{ $name }}</li>
            @endforeach
        </ul>
    </div>
@endif
