export interface Book {
    id: number;
    title: string;
    author: string;
    details: string | null;
    image_path: string | null;
    image_url: string | null;
    book_url: string | null;
    description: string | null;
    is_purchased: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface BookStats {
    total: number;
    purchased: number;
    unpurchased: number;
}
