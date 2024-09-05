<?php
/*
Plugin Name: CSV Post Importer
Description: Importa posts de um arquivo CSV, incluindo tags e imagens de destaque. Desenvolvido por Jean.
Version: 1.1
Author: Jean
Author URI: mailto:jean.carlos@uios.com.br
Plugin URI: https://uios.com.br/wordpress/plugins/uios-all-import-free
License: MIT
License URI: https://opensource.org/licenses/MIT
*/

/*
MIT License

Copyright (c) 2024 Jean

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

// Função para adicionar a página de administração
function cpi_add_admin_menu() {
    add_menu_page('CSV Post Importer', 'CSV Post Importer', 'manage_options', 'csv-post-importer', 'cpi_admin_page');
}
add_action('admin_menu', 'cpi_add_admin_menu');

// Função para exibir a página de administração
function cpi_admin_page() {
    ?>
    <h1>Importar Posts via CSV</h1>
    <form enctype="multipart/form-data" method="post" action="">
        <input type="file" name="csv_file" />
        <input type="submit" name="submit" value="Importar" />
    </form>
    <?php
    if (isset($_POST['submit'])) {
        cpi_import_csv();
    }
}

// Função para importar o CSV
function cpi_import_csv() {
    if (!empty($_FILES['csv_file']['tmp_name'])) {
        $csv_file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        while (($row = fgetcsv($csv_file)) !== FALSE) {
            $post_data = array(
                'post_title'    => $row[0],
                'post_content'  => $row[1],
                'post_status'   => 'publish',
                'post_author'   => 1,
                'post_category' => array(1)
            );
            $post_id = wp_insert_post($post_data);

            // Adicionar tags
            if (!empty($row[2])) {
                $tags = explode(',', $row[2]);
                wp_set_post_tags($post_id, $tags);
            }

            // Adicionar imagem de destaque
            if (!empty($row[3])) {
                $image_url = $row[3];
                $image_id = cpi_upload_image($image_url);
                if ($image_id) {
                    set_post_thumbnail($post_id, $image_id);
                }
            }
        }
        fclose($csv_file);
        echo '<p>Importação concluída!</p>';
    }
}

// Função para fazer upload da imagem
function cpi_upload_image($image_url) {
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    $filename = basename($image_url);
    if (wp_mkdir_p($upload_dir['path'])) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }
    file_put_contents($file, $image_data);

    $wp_filetype = wp_check_filetype($filename, null);
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     => sanitize_file_name($filename),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );
    $attach_id = wp_insert_attachment($attachment, $file);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    wp_update_attachment_metadata($attach_id, $attach_data);

    return $attach_id;
}
?>
