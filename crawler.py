import os

# Target folders
TARGET_FOLDERS = [
    "app/Http/Controllers",
    "routes"
]

# File types to include
INCLUDE_EXTENSIONS = {".php"}

OUTPUT_FILE = "laravel_api_dump.txt"


def crawl_laravel(root_dir):
    with open(OUTPUT_FILE, "w", encoding="utf-8") as outfile:

        for folder in TARGET_FOLDERS:
            target_path = os.path.join(root_dir, folder)

            if not os.path.exists(target_path):
                print(f"⚠️ Skipping missing folder: {folder}")
                continue

            for dirpath, dirnames, filenames in os.walk(target_path):

                for file in sorted(filenames):
                    if not file.endswith(tuple(INCLUDE_EXTENSIONS)):
                        continue

                    full_path = os.path.join(dirpath, file)
                    relative_path = os.path.relpath(full_path, root_dir)

                    try:
                        with open(full_path, "r", encoding="utf-8") as f:
                            content = f.read()
                    except Exception as e:
                        content = f"[ERROR READING FILE: {e}]"

                    # Write formatted output
                    outfile.write("\n==============================\n")
                    outfile.write(f"{relative_path}\n")
                    outfile.write("==============================\n\n")
                    outfile.write(content)
                    outfile.write("\n\n")

    print(f"✅ Done! Output saved to {OUTPUT_FILE}")


if __name__ == "__main__":
    project_root = os.getcwd()  # run from Laravel root
    crawl_laravel(project_root)