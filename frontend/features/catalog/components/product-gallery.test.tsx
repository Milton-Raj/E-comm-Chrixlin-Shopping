import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { ProductGallery } from "./product-gallery";

const images = [
  { url: "http://api.test/storage/a.jpg", alt: "Front" },
  { url: "http://api.test/storage/b.jpg", alt: "Side" },
];
const video = { youtube_id: "dQw4w9WgXcQ", thumbnail: "https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg" };

describe("ProductGallery", () => {
  it("shows a single photo without arrows or thumbnails", () => {
    render(<ProductGallery images={[images[0]!]} name="Rose Candle" />);

    expect(screen.getByRole("img", { name: "Front" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Next photo" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /Show item/ })).not.toBeInTheDocument();
  });

  it("puts photos and the video in one scrollable strip with a thumbnail for each", () => {
    render(<ProductGallery images={images} videos={[video]} name="Rose Candle" />);

    expect(screen.getAllByRole("group")).toHaveLength(3);
    expect(screen.getAllByRole("button", { name: /Show item/ })).toHaveLength(3);
    expect(screen.getByRole("button", { name: "Next photo" })).toBeEnabled();
    expect(screen.getByRole("button", { name: "Previous photo" })).toBeDisabled();
  });

  it("loads the YouTube player only after the shopper taps play", async () => {
    render(<ProductGallery images={images} videos={[video]} name="Rose Candle" />);
    expect(document.querySelector("iframe")).toBeNull();

    await userEvent.click(screen.getByRole("button", { name: "Play video: Rose Candle" }));

    const frame = screen.getByTitle("Rose Candle video");
    expect(frame.getAttribute("src")).toMatch(/^https:\/\/www\.youtube-nocookie\.com\/embed\/dQw4w9WgXcQ\?autoplay=1/);
  });

  it("works with only a video", () => {
    render(<ProductGallery images={[]} videos={[video]} name="Rose Candle" />);

    expect(screen.getByRole("button", { name: "Play video: Rose Candle" })).toBeInTheDocument();
  });
});
