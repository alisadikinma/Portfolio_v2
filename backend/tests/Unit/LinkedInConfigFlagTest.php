<?php

test('repurpose_source_mirror_regenerate defaults to true', function () {
    expect(config('linkedin.repurpose_source_mirror_regenerate'))->toBeTrue();
});

test('auto-pipeline repurpose_source_mirror default is unchanged (false)', function () {
    expect(config('linkedin.repurpose_source_mirror'))->toBeFalse();
});
