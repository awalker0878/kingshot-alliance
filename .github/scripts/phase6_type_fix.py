from pathlib import Path

path = Path('resources/js/pages/Platform/Administration/Index.vue')
text = path.read_text()
old = "const featureForm = useForm({\n  feature_key: '',\n  enabled: true,\n  configuration: {} as Record<string, unknown>,\n});"
new = "const featureForm = useForm({\n  feature_key: '',\n  enabled: true,\n});"
if old not in text:
    raise SystemExit('feature form anchor not found')
path.write_text(text.replace(old, new, 1))
